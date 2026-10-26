<?php

namespace Tests\Feature\Public;

use App\Models\GenerationJob;
use App\Models\Site;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function site(array $attrs = []): Site
    {
        return Site::create(array_merge([
            'slug' => 'demo-'.fake()->lexify('????'), 'name' => 'Démo', 'sector' => 'service', 'status' => 'preview',
            'source' => 'joow-public', 'site_data' => ['content' => ['hero_title' => 'x']],
        ], $attrs));
    }

    public function test_show_exposes_email_state_and_expiry(): void
    {
        config(['services.preview_ttl_days' => 7]);
        $site = $this->site(['created_at' => now()->subDays(2)]);

        $this->withSession(['joow_sites' => [$site->slug]])
            ->get(route('public.site', $site->slug))
            ->assertInertia(fn ($p) => $p->where('can_edit', true)->where('has_email', false)->where('expires_in_days', 5));
    }

    public function test_guest_creator_can_attach_email_which_creates_an_account_and_sends_the_welcome(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $site = $this->site();

        $this->withSession(['joow_sites' => [$site->slug]])
            ->postJson(route('public.site.email', $site->slug), ['email' => 'Pro@Exemple.fr'])
            ->assertOk()->assertJson(['ok' => true, 'sent' => true]);

        $site->refresh();
        $user = User::where('email', 'pro@exemple.fr')->firstOrFail();
        $this->assertSame($user->id, $site->user_id, 'site rattaché au compte créé');
        $this->assertSame('pro@exemple.fr', $site->owner_email);
        $this->assertNull($user->password_set_at);
        $this->assertSame('site', $user->source);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('pro@exemple.fr') && str_contains($m->subjectLine, 'est prêt'));
    }

    public function test_email_during_generation_creates_the_account_without_sending_yet(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $site = $this->site(['status' => 'generating']);

        $this->withSession(['joow_sites' => [$site->slug]])
            ->postJson(route('public.site.email', $site->slug), ['email' => 'pro@exemple.fr'])
            ->assertOk()->assertJson(['sent' => false]);
        $this->assertNotNull($site->fresh()->user_id);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_stranger_cannot_attach_email(): void
    {
        $site = $this->site();
        $this->postJson(route('public.site.email', $site->slug), ['email' => 'pirate@exemple.fr'])->assertForbidden();
        $this->assertNull($site->fresh()->owner_email);
    }

    public function test_studio_requires_email_for_guest_sites(): void
    {
        $site = $this->site();
        $this->withSession(['joow_sites' => [$site->slug]])
            ->get(route('sites.editor', $site->slug))
            ->assertRedirect(route('public.site', $site->slug).'?studio=1');

        $site->update(['owner_email' => 'pro@exemple.fr']);
        $this->withSession(['joow_sites' => [$site->slug]])->get(route('sites.editor', $site->slug))->assertOk();
    }

    public function test_purge_removes_only_anonymous_expired_previews(): void
    {
        config(['services.preview_ttl_days' => 7]);
        $old = $this->site(['created_at' => now()->subDays(9)]);
        GenerationJob::create(['status' => 'completed', 'sector' => 'service', 'site_slug' => $old->slug, 'input' => []]);
        $recent = $this->site(['created_at' => now()->subDays(3)]);
        $withEmail = $this->site(['created_at' => now()->subDays(30), 'owner_email' => 'pro@exemple.fr']);
        $owned = $this->site(['created_at' => now()->subDays(30), 'user_id' => User::factory()->create()->id]);
        $paid = $this->site(['created_at' => now()->subDays(30), 'status' => 'paid', 'paid_at' => now()]);

        $this->artisan('sites:purge-previews')->assertSuccessful();

        $this->assertDatabaseMissing('sites', ['id' => $old->id]);
        $this->assertDatabaseMissing('generation_jobs', ['site_slug' => $old->slug]);
        foreach ([$recent, $withEmail, $owned, $paid] as $kept) {
            $this->assertDatabaseHas('sites', ['id' => $kept->id]);
        }
    }
}
