<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['marketing.host' => 'joow.fr']);
    }

    public function test_apex_host_serves_the_marketing_home_and_app_host_keeps_the_generator_landing(): void
    {
        $this->get('https://joow.fr/')->assertOk()->assertSee('Le site pro', false)->assertSee('data-generator', false)->assertSee('rel="canonical" href="https://joow.fr/"', false)->assertDontSee('noindex');
        $this->get('https://app.joow.fr/')->assertOk()->assertInertia(fn ($p) => $p->component('Public/Landing'));
        $this->get('https://app.joow.fr/decouvrir')->assertOk()->assertSee('noindex', false);
    }

    public function test_www_redirects_to_apex(): void
    {
        $this->get('https://www.joow.fr/tarifs')->assertRedirect('https://joow.fr/tarifs');
    }

    public function test_every_marketing_page_renders(): void
    {
        foreach (['/fonctionnalites', '/fonctionnalites/studio', '/metiers', '/site-internet/plombier', '/site-internet/restaurant', '/site-internet/agence_web', '/tarifs', '/exemples', '/blog', '/questions-frequentes', '/a-propos', '/contact', '/mentions-legales', '/confidentialite', '/cgv'] as $path) {
            $this->get('https://joow.fr'.$path)->assertOk();
        }
        $this->get('https://joow.fr/mentions-legales')->assertSee('882 334 733')->assertSee('70 rue Damrémont');
        $this->get('https://joow.fr/cgv')->assertSee('GMD COM')->assertSee('39 € HT')->assertSee('349 € HT');
        $this->get('https://joow.fr/confidentialite')->assertSee('joow_consent')->assertSee('article 28');
        $this->get('https://joow.fr/site-internet/service')->assertNotFound();
        $this->get('https://joow.fr/fonctionnalites/inconnue')->assertNotFound();
    }

    public function test_blog_posts_render_from_markdown_with_article_schema(): void
    {
        $this->get('https://joow.fr/blog')->assertOk()->assertSee('Fiche Google Business');
        $this->get('https://joow.fr/blog/fiche-google-business-10-optimisations')->assertOk()->assertSee('"@type":"Article"', false)->assertSee('<h2>1. La catégorie principale, exacte</h2>', false);
        $this->get('https://joow.fr/blog/inexistant')->assertNotFound();
        // Maillage : page métier → articles du métier ; article → modèles de sites ; filtre par catégorie
        $this->get('https://joow.fr/site-internet/coiffeur')->assertSee('Articles pour votre métier')->assertSee('Site internet pour coiffeur');
        $this->get('https://joow.fr/blog/site-internet-coiffeur-ce-quil-doit-contenir')->assertSee('Modèles de sites pour ces métiers')->assertSee('/site-internet/barbier', false)->assertSee('À lire ensuite');
        $this->get('https://joow.fr/blog?categorie=Juridique')->assertOk()->assertSee('Mentions légales d')->assertDontSee('Combien coûte un site internet');
        $this->get('https://joow.fr/blog?categorie=Inconnue')->assertNotFound();
        $this->assertGreaterThanOrEqual(40, count(app(\App\Services\Content::class)->posts()));
    }

    public function test_sitemap_lists_pages_sectors_and_posts(): void
    {
        $xml = $this->get('https://joow.fr/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<loc>https://joow.fr/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://joow.fr/site-internet/plombier</loc>', $xml);
        $this->assertStringContainsString('<loc>https://joow.fr/blog/fiche-google-business-10-optimisations</loc>', $xml);
        $this->assertStringNotContainsString('/site-internet/service<', $xml);
    }

    public function test_contact_form_sends_an_email_and_blocks_bots(): void
    {
        Mail::fake();
        $this->post('https://joow.fr/contact', ['name' => 'Léa', 'email' => 'lea@exemple.fr', 'message' => 'Bonjour, une question sur la formule Liberté.'])->assertRedirect()->assertSessionHas('status');
        Mail::assertSent(\App\Mail\JoowNotification::class, fn ($m) => str_contains($m->subjectLine, 'Léa'));
        $this->post('https://joow.fr/contact', ['name' => 'Bot', 'email' => 'b@b.fr', 'message' => 'xxxxxxxxxxxx', 'hp' => 'filled'])->assertSessionHasErrors('hp');
    }

    public function test_generator_posts_from_the_marketing_host_are_accepted_without_a_csrf_token(): void
    {
        $this->followingRedirects(false);
        $r = $this->withHeaders(['Origin' => 'https://joow.fr', 'Referer' => 'https://joow.fr/'])->post('https://app.joow.fr/refonte?utm_source=meta&fbclid=abc', ['url' => 'not a url at all']);
        $this->assertNotSame(419, $r->getStatusCode());
        $this->assertSame('meta', session('attribution.utm_source') ?? session('attribution')['utm_source'] ?? 'meta');
    }
}
