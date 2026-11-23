<?php

namespace Tests\Feature\Public;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LvReservationsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_missed_requests_are_recovered_and_the_owner_is_notified(): void
    {
        Mail::fake();
        Http::fake(['local-visibility.com/*' => Http::response(['success' => true, 'config' => ['units' => [['id' => 'unit_main'], ['id' => 'unit_2']]]])]);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'hebergement', 'status' => 'paid', 'source' => 'legacy', 'owner_email' => 'proprio@yahoo.fr', 'site_data' => []]);
        $r1 = Room::create(['site_id' => $site->id, 'site_slug' => 'clos', 'name' => 'La Terrasse', 'sort_order' => 0]);
        $r2 = Room::create(['site_id' => $site->id, 'site_slug' => 'clos', 'name' => 'Le Lodge', 'sort_order' => 1]);
        $file = sys_get_temp_dir().'/lv-res-'.uniqid().'.json';
        file_put_contents($file, json_encode(['reservations' => [
            ['slug' => 'clos', 'unit_id' => 'unit_2', 'checkin' => '2027-04-04', 'checkout' => '2027-04-07', 'guests' => 2, 'guest_name' => 'Marie Martin', 'guest_email' => 'marie@exemple.fr', 'guest_phone' => '0600000000', 'guest_message' => 'Anniversaire', 'created_at' => '2026-11-02 10:00:00'],
            ['slug' => 'clos', 'unit_id' => 'unit_main', 'checkin' => '2027-05-01', 'checkout' => '2027-05-03', 'guest_name' => 'Ancienne', 'created_at' => '2026-09-01'],
            ['slug' => 'autre-site', 'checkin' => '2027-04-04', 'checkout' => '2027-04-07', 'guest_name' => 'Autre'],
        ]]));

        $this->artisan('lv:reservations', ['path' => $file, 'lv_slug' => 'clos', '--since' => '2026-10-18', '--notify' => true])->assertSuccessful();
        $b = RoomBooking::where('site_slug', 'clos')->get();
        $this->assertCount(1, $b);
        $this->assertSame($r2->id, $b[0]->room_id);
        $this->assertSame(3, (int) $b[0]->nights);
        $this->assertStringContainsString('Anniversaire', $b[0]->notes);
        Mail::assertSent(\App\Mail\JoowNotification::class, fn ($m) => $m->hasTo('proprio@yahoo.fr') && str_contains($m->render(), 'Marie Martin'));

        $this->artisan('lv:reservations', ['path' => $file, 'lv_slug' => 'clos', '--since' => '2026-10-18'])->assertSuccessful();
        $this->assertSame(1, RoomBooking::where('site_slug', 'clos')->count()); // pas de doublon
    }
}
