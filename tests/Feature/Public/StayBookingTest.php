<?php

namespace Tests\Feature\Public;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Models\User;
use App\Services\Modules\IcalSync;
use App\Services\Modules\StayPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StayBookingTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'chambre-hote', 'status' => 'published', 'source' => 'joow-public', 'owner_email' => 'hote@exemple.fr',
            'modules' => ['rooms' => ['enabled' => true]], 'site_data' => []]);
    }

    private function room(array $attrs = []): Room
    {
        return Room::create(array_merge([
            'site_id' => $this->site->id, 'site_slug' => 'clos', 'name' => 'La Terrasse', 'capacity' => 2, 'price_night' => 180, 'min_nights' => 2,
            'seasons' => [['name' => 'Haute saison', 'from' => '05-15', 'to' => '09-15', 'price' => 250, 'weekend_price' => null, 'min_nights' => 3]],
        ], $attrs));
    }

    public function test_quote_applies_seasons_weekends_fees_and_tax(): void
    {
        $room = $this->room(['weekend_price' => 200, 'cleaning_fee' => 40, 'included_guests' => 2, 'extra_guest_fee' => 25, 'tourist_tax' => 1.5, 'capacity' => 4]);
        $p = app(StayPlanner::class);

        // Mercredi 4 → samedi 7 novembre 2026 : mer + jeu à 180, ven à 200 (week-end)
        $q = $p->quote($room, '2027-11-03', '2027-11-06', 3);
        $this->assertTrue($q['ok']);
        $this->assertSame(3, $q['nights']);
        $this->assertSame(560.0, $q['lodging']);           // 180 + 180 + 200
        $this->assertSame(75.0, $q['extra_guests']);       // 1 pers. × 25 × 3 nuits
        $this->assertSame(13.5, $q['tax']);                // 1,5 × 3 pers. × 3 nuits
        $this->assertSame(688.5, $q['total']);             // + 40 de ménage

        // En haute saison : 250 la nuit, 3 nuits minimum
        $this->assertFalse($p->quote($room, '2027-07-14', '2027-07-16')['ok']);
        $q = $p->quote($room, '2027-07-14', '2027-07-17');
        $this->assertSame(750.0, $q['lodging']);
        $this->assertStringContainsString('Haute saison', $q['lines'][0]['label']);

        // Hors saison : 2 nuits minimum, capacité respectée
        $this->assertFalse($p->quote($room, '2027-11-03', '2027-11-04')['ok']);
        $this->assertFalse($p->quote($room, '2027-11-03', '2027-11-06', 5)['ok']);
    }

    public function test_season_can_span_new_year(): void
    {
        $room = $this->room(['seasons' => [['name' => 'Fêtes', 'from' => '12-20', 'to' => '01-03', 'price' => 300]]]);
        $p = app(StayPlanner::class);
        $this->assertSame(300.0, $p->night($room, \Carbon\Carbon::parse('2027-12-31'))['price']);
        $this->assertSame(300.0, $p->night($room, \Carbon\Carbon::parse('2028-01-02'))['price']);
        $this->assertSame(180.0, $p->night($room, \Carbon\Carbon::parse('2028-01-10'))['price']);
    }

    public function test_stay_request_enforces_rules_and_stores_breakdown(): void
    {
        $room = $this->room();
        $payload = ['check_in' => '2027-11-03', 'check_out' => '2027-11-04', 'guests' => 2, 'room_id' => $room->id, 'name' => 'Léa', 'phone' => '0600000000', 'email' => 'lea@exemple.fr'];

        $this->postJson('/api/site/clos/stay', $payload)->assertStatus(422)->assertJsonPath('ok', false);

        $this->postJson('/api/site/clos/stay', ['check_out' => '2027-11-05'] + $payload)->assertOk()->assertJsonPath('total', 360);
        $bk = RoomBooking::first();
        $this->assertSame('pending', $bk->status);
        $this->assertNotEmpty($bk->breakdown['lines']);

        // Dates déjà prises
        $this->postJson('/api/site/clos/stay', ['check_in' => '2027-11-04', 'check_out' => '2027-11-06'] + $payload)->assertStatus(422);
    }

    public function test_any_room_picks_the_cheapest_free_one_and_global_blocks_apply(): void
    {
        $a = $this->room(['name' => 'Lodge', 'price_night' => 200, 'seasons' => []]);
        $b = $this->room(['name' => 'Terrasse', 'price_night' => 150, 'seasons' => []]);
        $payload = ['check_in' => '2027-11-03', 'check_out' => '2027-11-05', 'guests' => 2, 'name' => 'Léa', 'phone' => '0600000000'];

        $this->postJson('/api/site/clos/stay', $payload)->assertOk()->assertJsonPath('room', 'Terrasse');
        $this->postJson('/api/site/clos/stay', $payload)->assertOk()->assertJsonPath('room', 'Lodge');
        $this->postJson('/api/site/clos/stay', $payload)->assertStatus(422);

        // Fermeture de toute la maison : plus rien de libre
        RoomBlock::create(['site_slug' => 'clos', 'room_id' => null, 'start' => '2027-12-01', 'end' => '2027-12-10', 'source' => 'manual']);
        $this->postJson('/api/site/clos/stay', ['check_in' => '2027-12-02', 'check_out' => '2027-12-04'] + $payload)->assertStatus(422);
    }

    public function test_calendar_returns_prices_and_availability(): void
    {
        $room = $this->room(['seasons' => []]);
        RoomBooking::create(['site_slug' => 'clos', 'room_id' => $room->id, 'check_in' => '2027-11-10', 'check_out' => '2027-11-12', 'guests' => 2, 'nights' => 2, 'name' => 'X', 'status' => 'confirmed']);

        $days = collect($this->getJson('/api/site/clos/calendar?from=2027-11-09&days=4')->assertOk()->json('days'))->keyBy('d');
        $this->assertTrue($days['2027-11-09']['a']);
        $this->assertFalse($days['2027-11-10']['a']);
        $this->assertFalse($days['2027-11-11']['a']);
        $this->assertTrue($days['2027-11-12']['a']);
        $this->assertEquals(180, $days['2027-11-09']['p']);
        $this->assertSame(2, $days['2027-11-09']['m']);
    }

    public function test_instant_booking_confirms_directly(): void
    {
        $this->site->update(['modules' => ['rooms' => ['enabled' => true, 'instant' => true]]]);
        $room = $this->room(['seasons' => []]);
        $this->postJson('/api/site/clos/stay', ['check_in' => '2027-11-03', 'check_out' => '2027-11-05', 'guests' => 2, 'room_id' => $room->id, 'name' => 'Léa', 'phone' => '06', 'email' => 'lea@exemple.fr'])
            ->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_multiple_ical_feeds_and_export(): void
    {
        $room = $this->room(['ical_urls' => [['label' => 'Booking', 'url' => 'https://booking.test/a.ics'], ['label' => 'Airbnb', 'url' => 'https://airbnb.test/b.ics']]]);
        $ics = fn ($uid, $s, $e) => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:$uid\r\nDTSTART;VALUE=DATE:$s\r\nDTEND;VALUE=DATE:$e\r\nSUMMARY:Reserved\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        Http::fake(['booking.test/*' => Http::response($ics('x1', '20271201', '20271204')), 'airbnb.test/*' => Http::response($ics('x1', '20271210', '20271212'))]);

        $this->assertSame(2, app(IcalSync::class)->syncRoom($room));
        $this->assertSame(2, RoomBlock::where('room_id', $room->id)->count()); // même UID dans deux flux : deux blocages

        // Un flux retiré : ses blocages disparaissent
        $room->update(['ical_urls' => [['label' => 'Booking', 'url' => 'https://booking.test/a.ics']]]);
        app(IcalSync::class)->syncRoom($room->fresh());
        $this->assertSame(1, RoomBlock::where('room_id', $room->id)->count());

        // Export : les réservations Joow, protégé par jeton
        RoomBooking::create(['site_slug' => 'clos', 'room_id' => $room->id, 'check_in' => '2027-11-10', 'check_out' => '2027-11-12', 'guests' => 2, 'nights' => 2, 'name' => 'X', 'status' => 'confirmed']);
        $url = $room->fresh()->exportUrl();
        $body = $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20271110', $body);
        $this->assertStringNotContainsString('Reserved', $body); // les blocages importés ne sont pas réexportés
        $this->get("/ical/chambre/{$room->id}/mauvais-jeton.ics")->assertNotFound();
    }

    public function test_owner_can_save_seasons_and_feeds(): void
    {
        $user = User::factory()->create(['email' => 'hote@exemple.fr']);
        $this->actingAs($user)->postJson('/sites/clos/chambres', [
            'name' => 'Chambre Ocean', 'capacity' => 2, 'price_night' => 95, 'weekend_price' => 110, 'min_nights' => 2,
            'seasons' => [['name' => 'Été', 'from' => '07-01', 'to' => '08-31', 'price' => 120, 'min_nights' => 3]],
            'ical_urls' => [['label' => 'Booking', 'url' => 'https://booking.test/a.ics']],
        ])->assertOk();
        $room = Room::firstWhere('name', 'Chambre Ocean');
        $this->assertSame('Été', $room->seasons[0]['name']);
        $this->assertSame(3, $room->seasons[0]['min_nights']);
        $this->assertCount(1, $room->icalFeeds());
        $this->actingAs($user)->getJson('/sites/clos/chambres')->assertOk()->assertJsonStructure(['rooms' => [['export_url']]]);
    }
}
