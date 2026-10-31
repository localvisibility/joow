<?php

namespace Tests\Feature\Public;

use App\Listeners\StripeWebhookListener;
use App\Models\LandingPage;
use App\Models\Site;
use App\Models\User;
use App\Services\Ads\AbTest;
use App\Services\Ads\AdsStudio;
use App\Services\Ads\LandingPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class JoowAdsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sites_path' => sys_get_temp_dir().'/joow-ads-'.uniqid()]);
        $this->owner = User::factory()->create(['email' => 'client@exemple.fr']);
        $this->site = Site::create(['slug' => 'plombier-ab12', 'name' => 'Plomberie Dupont', 'sector' => 'plombier', 'city' => 'Lyon', 'status' => 'published', 'hosting_plan' => 'pro',
            'source' => 'joow-public', 'user_id' => $this->owner->id, 'owner_email' => 'client@exemple.fr', 'phone' => '06 11 22 33 44', 'rating' => 4.8, 'reviews_count' => 120,
            'site_data' => ['content' => ['hero_title' => 'Votre plombier à Lyon'], 'images' => ['hero' => 'https://app.joow.fr/media/x/hero.jpg']]]);
    }

    private function activate(): void
    {
        $d = $this->site->site_data;
        $d['addons']['ads'] = ['active' => true];
        $this->site->forceFill(['site_data' => $d])->save();
    }

    public function test_module_is_locked_until_subscribed(): void
    {
        $this->actingAs($this->owner)->get('/publicite')->assertOk()->assertInertia(fn ($p) => $p->component('Ads')->where('active', false)->where('online', true));
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pack', ['goal' => 'calls'])->assertStatus(402);
    }

    public function test_pack_generation_and_landing_from_pack(): void
    {
        $this->activate();
        $this->mock(AdsStudio::class, function ($m) {
            $m->shouldReceive('pack')->andReturn(['strategy' => ['summary' => 'S'], 'meta_ads' => [['hook' => 'H', 'primary_text' => 'T', 'headline' => 'Hd', 'image' => null]],
                'landing' => ['title' => 'Dépannage', 'path' => 'depannage', 'variants' => [['headline' => 'A1', 'cta' => 'Appeler'], ['headline' => 'B1', 'cta' => 'Appeler']]]]);
        });
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pack', ['goal' => 'calls', 'offer' => 'Devis gratuit'])->assertOk()->assertJsonPath('pack.strategy.summary', 'S');

        $page = $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pages', ['goal' => 'calls', 'title' => 'Dépannage', 'path' => 'depannage',
            'variants' => [['headline' => 'A1'], ['headline' => 'B1']]])->assertOk()->json('page');
        $this->assertSame(['A', 'B'], array_column($page['variants'], 'key'));
        $this->assertSame('https://plombier-ab12.joow.fr/o/depannage/', $page['url']);

        // Publication : fichier statique avec les deux variantes
        $this->actingAs($this->owner)->postJson("/publicite/plombier-ab12/pages/{$page['id']}/action", ['action' => 'publish'])->assertOk()->assertJsonPath('page.status', 'live');
        $html = file_get_contents(config('services.sites_path').'/plombier-ab12/o/depannage/index.html');
        $this->assertStringContainsString('data-v="A"', $html);
        $this->assertStringContainsString('data-v="B"', $html);
        $this->assertStringContainsString('tel:0611223344', $html);
    }

    public function test_hits_and_ab_verdict(): void
    {
        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'offre', 'title' => 'Offre', 'goal' => 'leads', 'status' => 'live',
            'variants' => [['key' => 'A', 'headline' => 'a'], ['key' => 'B', 'headline' => 'b']]]);
        $this->postJson("/api/site/plombier-ab12/lp/{$lp->id}/hit", ['v' => 'A', 't' => 'view'])->assertOk();
        $this->postJson("/api/site/plombier-ab12/lp/{$lp->id}/hit", ['v' => 'A', 't' => 'call'])->assertOk();
        $r = app(AbTest::class)->results($lp);
        $this->assertSame(1, $r['variants']['A']['views']);
        $this->assertSame(1, $r['variants']['A']['calls']);
        $this->assertSame(1, $r['variants']['A']['conversions']);
        $this->assertSame('collecting', $r['verdict']['status']);

        $ab = app(AbTest::class);
        $this->assertSame('winner', $ab->verdict(['A' => ['views' => 1000, 'conversions' => 30], 'B' => ['views' => 1000, 'conversions' => 70]])['status']);
        $this->assertSame('B', $ab->verdict(['A' => ['views' => 1000, 'conversions' => 30], 'B' => ['views' => 1000, 'conversions' => 70]])['winner']);
        $this->assertSame('tie', $ab->verdict(['A' => ['views' => 200, 'conversions' => 10], 'B' => ['views' => 200, 'conversions' => 12]])['status']);
    }

    public function test_addon_webhooks_never_touch_the_hosting_subscription(): void
    {
        $listener = app(StripeWebhookListener::class);
        $listener->handle(new WebhookReceived(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_ads', 'payment_status' => 'paid', 'subscription' => 'sub_ads', 'metadata' => ['type' => 'addon_ads', 'site_slug' => 'plombier-ab12']]]]));
        $this->assertTrue($this->site->fresh()->adsActive());

        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'offre', 'title' => 'Offre', 'goal' => 'leads', 'status' => 'live', 'variants' => [['key' => 'A', 'headline' => 'a']]]);
        $listener->handle(new WebhookReceived(['type' => 'customer.subscription.deleted', 'data' => ['object' => [
            'id' => 'sub_ads', 'customer' => 'cus_1', 'metadata' => ['type' => 'addon_ads', 'site_slug' => 'plombier-ab12']]]]));

        $site = $this->site->fresh();
        $this->assertFalse($site->adsActive());
        $this->assertSame('published', $site->status);
        $this->assertNotSame('canceled', $site->subscription_status); // l'hébergement n'est pas résilié
        $this->assertSame('paused', $lp->fresh()->status);
    }

    public function test_preview_renders_for_owner(): void
    {
        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'offre', 'title' => 'Offre', 'goal' => 'calls', 'status' => 'draft',
            'variants' => [['key' => 'A', 'headline' => 'Dépannage en 1 h', 'bullets' => ['7j/7']]]]);
        $this->actingAs($this->owner)->get("/publicite/plombier-ab12/pages/{$lp->id}/apercu")->assertOk()->assertSee('Dépannage en 1 h');
        $this->assertStringContainsString('Plomberie Dupont', app(LandingPublisher::class)->html($lp->setRelation('site', $this->site)));
    }

    public function test_ai_visuals_use_monthly_quota_then_credits(): void
    {
        $this->activate();
        config(['services.gemini.key' => 'k', 'joow.ads_visuals_per_month' => 1]);
        $img = imagecreatetruecolor(20, 20); ob_start(); imagepng($img); $png = ob_get_clean();
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($png)]]]]]]])]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->site->refresh()->forceFill(['ai_credits_monthly' => 0, 'ai_credits_wallet' => 0, 'ai_credits_reset_at' => now()->addWeek()])->save();

        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/visuels', ['brief' => 'Plombier souriant devant un chauffe-eau', 'format' => 'portrait'])
            ->assertOk()->assertJsonPath('paid_with', 'quota')->assertJsonPath('usage.left', 0);
        // Quota épuisé et pas de crédits : refusé
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/visuels', ['brief' => 'Autre visuel', 'format' => 'square'])->assertStatus(402);
        // Avec des crédits : 2 crédits débités
        $this->site->refresh()->forceFill(['ai_credits_wallet' => 5])->save();
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/visuels', ['brief' => 'Autre visuel', 'format' => 'square'])->assertOk()->assertJsonPath('paid_with', 'credits');
        $this->assertSame(3, (int) $this->site->fresh()->ai_credits_wallet);
        $this->assertCount(2, $this->site->fresh()->site_data['addons']['ads']['visuals']);
    }

    public function test_multistep_form_content_is_cleaned_and_rendered(): void
    {
        $c = \App\Http\Controllers\AdsController::cleanContent(['form' => ['title' => 'Votre projet', 'steps' => [
            ['question' => 'Type de logement ?', 'type' => 'choice', 'options' => ['Maison', 'Appartement']],
            ['question' => 'Surface ?', 'type' => 'choice', 'options' => ['Seule']], // 1 option : devient texte libre
            ['question' => '', 'type' => 'text'],
        ], 'ask_postcode' => true], 'faq' => [['q' => 'Prix ?', 'a' => 'Devis gratuit.']]], 'leads');
        $this->assertCount(2, $c['form']['steps']);
        $this->assertSame('text', $c['form']['steps'][1]['type']);
        $this->assertTrue($c['form']['ask_postcode']);

        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'pac', 'title' => 'PAC', 'goal' => 'leads', 'status' => 'draft',
            'variants' => [['key' => 'A', 'headline' => 'Réduisez vos factures']], 'content' => $c]);
        $html = app(LandingPublisher::class)->html($lp->setRelation('site', $this->site));
        $this->assertStringContainsString('Type de logement ?', $html);
        $this->assertStringContainsString('data-val="Appartement"', $html);
        $this->assertStringContainsString('name="postcode"', $html);
        $this->assertStringContainsString('Prix ?', $html);
    }

    public function test_campaign_concepts_are_generated_and_never_invent_reviews(): void
    {
        $this->activate();
        config(['services.gemini.key' => 'k']);
        $json = json_encode(['creatives' => [
            ['axis' => 'preuve sociale', 'layout' => 'avis', 'hook' => 'Ils nous recommandent', 'review' => 0, 'photo' => 0, 'cta' => 'Devis'],
            ['axis' => 'bénéfice', 'layout' => 'chiffre', 'hook' => 'Moins de factures', 'stat' => '-60 %', 'use_ai_image' => true, 'scene' => 'Maison avec pompe à chaleur'],
            ['axis' => 'curiosité', 'layout' => 'inconnu', 'hook' => 'Et si…'],
        ], 'carousel' => ['slides' => [['title' => 'Le problème', 'text' => 'Factures']]], 'landing_hint' => 'Économiser sur le chauffage']);
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [['text' => $json]]]]]])]);
        $pack = \App\Models\AdsPack::create(['site_id' => $this->site->id, 'goal' => 'leads', 'pack' => ['strategy' => ['summary' => 'S'], 'meta_ads' => []]]);

        $c = $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/campagne', ['pack_id' => $pack->id])->assertOk()->json('campaign');
        $this->assertSame('impact', $c['creatives'][0]['layout']); // aucun avis Google réel : pas de citation
        $this->assertSame('chiffre', $c['creatives'][1]['layout']);
        $this->assertSame([], $c['creatives'][1]['renders']); // la pub finale est créée ensuite, concept par concept
        $this->assertSame('impact', $c['creatives'][2]['layout']);
        $this->assertSame('c2', $c['creatives'][1]['key']);
        $this->assertNotEmpty($pack->fresh()->pack['campaign']['carousel']['slides']);
    }

    public function test_widget_rules_and_message_match(): void
    {
        $c = \App\Http\Controllers\AdsController::cleanContent(['widget' => ['type' => 'savings', 'savings' => ['pct_min' => 0, 'pct_max' => 0]]], 'leads');
        $this->assertSame('none', $c['widget']['type']); // pas de pourcentage : pas de simulateur
        $c = \App\Http\Controllers\AdsController::cleanContent(['widget' => ['type' => 'estimate', 'estimate' => ['options' => ['Petit', 'Grand']]]], 'leads');
        $this->assertSame('none', $c['widget']['type']); // jamais de prix inventé
        $c = \App\Http\Controllers\AdsController::cleanContent(['theme' => 'premium', 'widget' => ['type' => 'savings', 'savings' => ['pct_min' => 30, 'pct_max' => 50, 'min' => 500, 'max' => 4000]],
            'match' => ['c1' => ['headline' => 'Moins de factures', 'image' => 'https://app.joow.fr/media/x/a.jpg']]], 'leads');
        $this->assertSame('savings', $c['widget']['type']);
        $this->assertSame('premium', $c['theme']);

        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'p', 'title' => 'P', 'goal' => 'leads', 'status' => 'draft',
            'variants' => [['key' => 'A', 'headline' => 'Titre A']], 'content' => $c]);
        $html = app(LandingPublisher::class)->html($lp->setRelation('site', $this->site));
        $this->assertStringContainsString('id="tool-sav"', $html);
        $this->assertStringContainsString('Moins de factures', $html); // accroche de la pub c1 disponible pour la page
    }

    public function test_adaptive_split_favours_the_better_variant(): void
    {
        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'ab', 'title' => 'AB', 'goal' => 'leads', 'status' => 'live',
            'variants' => [['key' => 'A', 'headline' => 'a'], ['key' => 'B', 'headline' => 'b']]]);
        \Illuminate\Support\Facades\DB::table('landing_stats')->insert([
            ['landing_id' => $lp->id, 'variant' => 'A', 'day' => now()->toDateString(), 'views' => 400, 'conversions' => 8, 'calls' => 0],
            ['landing_id' => $lp->id, 'variant' => 'B', 'day' => now()->toDateString(), 'views' => 400, 'conversions' => 40, 'calls' => 0],
        ]);
        $picks = collect(range(1, 200))->map(fn () => app(AbTest::class)->pick($lp))->countBy();
        $this->assertGreaterThan(180, $picks['B'] ?? 0);
        $this->getJson("/api/site/plombier-ab12/lp/{$lp->id}/pick")->assertOk()->assertJsonStructure(['v']);
        $lp->update(['winner' => 'A']);
        $this->assertSame('A', app(AbTest::class)->pick($lp->fresh()));
    }

    public function test_full_ad_is_rendered_with_quality_control_and_declined(): void
    {
        $this->activate();
        config(['services.gemini.key' => 'k', 'joow.ads_visuals_per_month' => 10]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $img = imagecreatetruecolor(20, 25); ob_start(); imagepng($img); $png = ob_get_clean();
        $calls = 0;
        \Illuminate\Support\Facades\Http::fake(function ($req) use ($png, &$calls) {
            if (str_contains($req->url(), 'image') && str_contains($req->url(), 'generateContent')) {
                $calls++;

                return \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['data' => base64_encode($png)]]]]]]]);
            }
            if (str_contains($req->url(), 'generateContent')) { // contrôle qualité : 1re version refusée, 2e acceptée
                $ok = $calls >= 2;

                return \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['text_ok' => $ok, 'score' => $ok ? 9 : 4, 'issues' => $ok ? [] : ['« Économisez » mal orthographié']])]]]]]]);
            }

            return \Illuminate\Support\Facades\Http::response(['models' => []]);
        });
        $pack = \App\Models\AdsPack::create(['site_id' => $this->site->id, 'goal' => 'calls', 'pack' => ['campaign' => ['creatives' => [
            ['key' => 'c1', 'axis' => 'bénéfice', 'headline' => 'Économisez 60 %', 'cta' => 'Appeler', 'art' => 'Plombier en intervention', 'refs' => [], 'renders' => []],
        ]]]]);

        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pub', ['pack_id' => $pack->id, 'key' => 'c1', 'format' => 'portrait'])->assertStatus(202);
        $state = $this->actingAs($this->owner)->getJson("/publicite/plombier-ab12/campagne/{$pack->id}")->assertOk()->json('creatives.0');
        $this->assertSame(9, $state['renders']['portrait']['qa']['score']);
        $this->assertEmpty($state['jobs']);
        $this->assertSame(2, $calls); // relance après le contrôle qualité
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pub', ['pack_id' => $pack->id, 'key' => 'c1', 'format' => 'story'])->assertStatus(202);
        $renders = $pack->fresh()->pack['campaign']['creatives'][0]['renders'];
        $this->assertArrayHasKey('portrait', $renders);
        $this->assertArrayHasKey('story', $renders);
        $this->assertSame(2, $this->site->fresh()->site_data['addons']['ads']['usage']['used']);
    }
}
