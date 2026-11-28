<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSiteFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_marks_a_site_as_test_and_it_leaves_stats_and_prospects(): void
    {
        config(['joow.admins' => ['admin@joow.fr']]);
        $admin = User::factory()->create(['email' => 'admin@joow.fr']);
        $a = Site::create(['slug' => 'tato', 'name' => 'Tatò', 'sector' => 'restaurant', 'status' => 'preview', 'owner_email' => 'moi@exemple.fr', 'site_data' => []]);
        Site::create(['slug' => 'vrai', 'name' => 'Vrai prospect', 'sector' => 'restaurant', 'status' => 'preview', 'owner_email' => 'client@exemple.fr', 'site_data' => []]);

        $this->actingAs($admin)->post(route('admin.sites.test', $a->id))->assertRedirect();
        $this->assertTrue($a->fresh()->is_test);
        $this->assertSame(['vrai'], Site::real()->pluck('slug')->all());

        $this->actingAs($admin)->get(route('admin.index', ['tab' => 'sites']))->assertOk()
            ->assertInertia(fn ($p) => $p->where('data.rows', fn ($rows) => collect($rows)->pluck('slug')->all() === ['vrai']));
        $this->actingAs($admin)->get(route('admin.index', ['tab' => 'sites', 'f' => 'test']))->assertOk()
            ->assertInertia(fn ($p) => $p->where('data.rows', fn ($rows) => collect($rows)->pluck('slug')->all() === ['tato']));

        $this->actingAs($admin)->post(route('admin.sites.test', $a->id));
        $this->assertFalse($a->fresh()->is_test);
    }
}
