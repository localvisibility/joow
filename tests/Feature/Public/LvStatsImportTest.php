<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\SiteStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LvStatsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_imported_from_bookings_and_google_analytics_without_duplicates(): void
    {
        Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'hebergement', 'status' => 'paid', 'source' => 'legacy', 'site_data' => []]);
        $dir = sys_get_temp_dir().'/lv-stats-'.uniqid();
        mkdir($dir);
        file_put_contents("$dir/b.json", json_encode(['reservations' => [
            ['slug' => 'clos', 'checkin' => '2026-07-01', 'checkout' => '2026-07-03', 'guest_name' => 'A', 'created_at' => '2026-05-10 09:00:00'],
            ['slug' => 'clos', 'checkin' => '2026-08-01', 'checkout' => '2026-08-03', 'guest_name' => 'B', 'created_at' => '2026-05-10 18:00:00'],
            ['slug' => 'clos', 'checkin' => '2026-09-01', 'checkout' => '2026-09-03', 'guest_name' => 'C', 'created_at' => '2026-06-02'],
            ['slug' => 'autre', 'checkin' => '2026-09-01', 'checkout' => '2026-09-03', 'guest_name' => 'D', 'created_at' => '2026-06-02'],
        ]]));
        // Export GA4 (rapport avec « Nième jour ») puis export d'exploration (Date AAAAMMJJ)
        file_put_contents("$dir/ga.csv", "# ----------------------------------------\n# Start date: 20260510\n# End date: 20260512\n# ----------------------------------------\n\nNth day,Views\n0000,120\n0001,\"1,045\"\n0002,80\n\n# Users\nNth day,Users\n0000,99\n");
        file_put_contents("$dir/ga2.csv", "\xEF\xBB\xBFDate;Vues;Sessions\n20260601;40;30\n20260602;55;41\n");

        SiteStat::create(['site_slug' => 'clos', 'day' => '2026-05-11', 'views' => 2000]); // déjà plus haut : conservé

        $this->artisan('lv:stats', ['lv_slug' => 'clos', '--bookings' => "$dir/b.json", '--ga' => "$dir/ga.csv"])->assertSuccessful();
        $this->artisan('lv:stats', ['lv_slug' => 'clos', '--ga' => "$dir/ga2.csv"])->assertSuccessful();
        $this->artisan('lv:stats', ['lv_slug' => 'clos', '--bookings' => "$dir/b.json", '--ga' => "$dir/ga.csv"])->assertSuccessful(); // rejoué

        $s = fn ($d) => SiteStat::where('site_slug', 'clos')->whereDate('day', $d)->first();
        $this->assertSame(2, (int) $s('2026-05-10')->bookings);
        $this->assertSame(120, (int) $s('2026-05-10')->views);
        $this->assertSame(2000, (int) $s('2026-05-11')->views);
        $this->assertSame(80, (int) $s('2026-05-12')->views);
        $this->assertSame(1, (int) $s('2026-06-02')->bookings);
        $this->assertSame(55, (int) $s('2026-06-02')->views);
        $this->assertSame(5, SiteStat::where('site_slug', 'clos')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'status' => 'paid', 'site_data' => []]);
        $f = sys_get_temp_dir().'/ga-'.uniqid().'.csv';
        file_put_contents($f, "Date,Views\n20260601,40\n");
        $this->artisan('lv:stats', ['lv_slug' => 'clos', '--ga' => $f, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, SiteStat::count());
    }
}
