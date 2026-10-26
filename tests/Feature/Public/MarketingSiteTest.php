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
        $this->get('https://joow.fr/site-internet/service')->assertNotFound();
        $this->get('https://joow.fr/fonctionnalites/inconnue')->assertNotFound();
    }

    public function test_blog_posts_render_from_markdown_with_article_schema(): void
    {
        $this->get('https://joow.fr/blog')->assertOk()->assertSee('Fiche Google Business');
        $this->get('https://joow.fr/blog/fiche-google-business-10-optimisations')->assertOk()->assertSee('"@type":"Article"', false)->assertSee('<h2>1. La catégorie principale, exacte</h2>', false);
        $this->get('https://joow.fr/blog/inexistant')->assertNotFound();
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
}
