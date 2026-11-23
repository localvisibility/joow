<?php

namespace Tests\Feature\Public;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LvBookingImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_booking_is_imported_and_the_page_uses_the_joow_module(): void
    {
        config(['services.sites_path' => sys_get_temp_dir().'/joow-lv-'.uniqid(), 'app.url' => 'https://app.joow.fr']);
        $in3 = now()->addDays(3)->toDateString();
        $in4 = now()->addDays(4)->toDateString();
        $in10 = now()->addDays(10)->toDateString();
        Http::fake([
            'local-visibility.com/*' => Http::response(['success' => true, 'config' => [
                'enabled' => true, 'notification_email' => 'clos@yahoo.fr',
                'units' => [
                    ['id' => 'unit_main', 'name' => 'La Terrasse dès', 'price' => 180, 'capacity' => 2, 'weekend_price' => 180, 'high_season_price' => 250,
                        'high_season_periods' => ['5b', '6a', '6b', '7a', '7b', '8a', '8b', '9a'], 'min_nights' => 2, 'max_nights' => 30, 'ical_urls' => ['booking' => '', 'other' => '']],
                    ['id' => 'unit_2', 'name' => 'Le Lodge dès', 'price' => 200, 'capacity' => 2, 'weekend_price' => 200, 'high_season_price' => 290,
                        'high_season_periods' => ['5b', '9a'], 'min_nights' => 2, 'max_nights' => 25, 'ical_urls' => []],
                ],
                'blocked_dates' => ['unit_main' => ['2020-01-01', $in3, $in4, $in10], 'unit_2' => []],
                'settings' => ['base_price' => 180],
            ]]),
        ]);
        $html = '<html><body><div id="lv-calendar-widget" data-slug="clos-du-petit-jesus-cassis" data-base-price="180"></div><script src="https://local-visibility.com/widgets/calendar.js?v=4"></script></body></html>';
        $site = Site::create(['slug' => 'clos-du-petit-jesus-cassis', 'name' => 'Le Clos du petit Jésus', 'sector' => 'chambres', 'status' => 'published', 'source' => 'legacy',
            'custom_domain' => 'clos-du-petit-jesus-cassis.com', 'html_content' => $html, 'site_data' => []]);

        // La page publiée contient le calendrier, la copie en base non (cas réel)
        @mkdir(config('services.sites_path').'/clos-du-petit-jesus-cassis', 0777, true);
        file_put_contents(config('services.sites_path').'/clos-du-petit-jesus-cassis/index.html', $html);
        $site->forceFill(['html_content' => '<html><body>ancienne copie</body></html>'])->saveQuietly();

        $this->artisan('lv:booking', ['lv_slug' => 'clos-du-petit-jesus-cassis'])->assertSuccessful();
        $this->assertStringContainsString('/widgets/booking.js', file_get_contents(config('services.sites_path').'/clos-du-petit-jesus-cassis/index.html'));

        $rooms = Room::where('site_id', $site->id)->orderBy('sort_order')->get();
        $this->assertSame(['La Terrasse', 'Le Lodge'], $rooms->pluck('name')->all());
        $this->assertEquals(180, $rooms[0]->price_night);
        $this->assertNull($rooms[0]->weekend_price);
        $this->assertSame(2, $rooms[0]->min_nights);
        $this->assertSame(['05-16', '09-15'], [$rooms[0]->seasons[0]['from'], $rooms[0]->seasons[0]['to']]);
        $this->assertEquals(250, $rooms[0]->seasons[0]['price']);
        $this->assertSame(2, RoomBlock::where('room_id', $rooms[0]->id)->count()); // passé ignoré, 2 périodes à venir

        $site->refresh();
        $this->assertTrue($site->moduleEnabled('rooms'));
        $this->assertSame('clos@yahoo.fr', $site->module('rooms')['notify_email']);
        $this->assertStringContainsString('https://app.joow.fr/widgets/booking.js', $site->html_content);
        $this->assertStringContainsString('data-joow-slug="clos-du-petit-jesus-cassis"', $site->html_content);
        $this->assertStringNotContainsString('local-visibility.com/widgets/calendar.js', $site->html_content);

        // Relancer la commande ne crée pas de doublons
        $this->artisan('lv:booking', ['lv_slug' => 'clos-du-petit-jesus-cassis'])->assertSuccessful();
        $this->assertSame(2, Room::where('site_id', $site->id)->count());
        $this->assertSame(2, RoomBlock::where('room_id', $rooms[0]->id)->count());

        // L'API publique répond pour le module
        $this->getJson('/api/site/clos-du-petit-jesus-cassis/rooms')->assertOk()->assertJsonCount(2, 'rooms');
        $this->getJson('/api/site/clos-du-petit-jesus-cassis/calendar?room='.$rooms[0]->id.'&days=20')->assertOk();
    }
}
