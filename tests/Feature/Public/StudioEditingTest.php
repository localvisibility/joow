<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteAgent;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Retours d'un client (Le Spazio) : photos des services, format des images, PDF, fond sombre, popup, IA honnête. */
class StudioEditingTest extends TestCase
{
    use RefreshDatabase;

    private function site(array $data = [], array $modules = []): Site
    {
        $c = ['hero_title' => 'Pizzeria napolitaine', 'hero_subtitle' => 'Four à bois', 'tagline' => 'Spazio', 'about_p1' => 'Depuis 2015.', 'about_p2' => 'Produits frais.',
            'services' => [['name' => 'Pizzas', 'desc' => 'Pâte 48 h', 'price' => ''], ['name' => 'Pâtes', 'desc' => 'Fraîches', 'price' => ''], ['name' => 'Desserts', 'desc' => 'Tiramisu', 'price' => '']],
            'faq' => [['q' => 'Réserver ?', 'a' => 'Oui.']], 'cta_text' => 'Réservez.'];

        return Site::create(['slug' => 'spazio-'.fake()->lexify('????'), 'name' => 'Le Spazio', 'sector' => 'restaurant', 'status' => 'published', 'source' => 'joow-public', 'owner_email' => 'spazio@exemple.fr',
            'modules' => $modules ?: ['booking' => true],
            'site_data' => array_replace_recursive(['business' => ['name' => 'Le Spazio', 'city' => 'Lyon', 'phone' => '04 72 00 00 00', 'address' => '1 rue de Lyon', 'photos' => []], 'content' => $c, 'images' => ['services' => 'https://exemple.fr/s0.jpg']], $data)]);
    }

    public function test_every_service_photo_is_editable_even_under_an_overlay_and_empty_ones_can_be_added(): void
    {
        $html = app(SiteRenderer::class)->html($this->site(), true);
        $this->assertStringContainsString('data-edit-img="images.services"', $html);           // photo 01 (carte vedette)
        $this->assertStringContainsString('data-edit-img="content.services.1.image"', $html);  // service sans photo : emplacement « Ajouter une photo »
        $this->assertStringContainsString('joow-img-chip', $html);                              // pastille « Changer la photo » au-dessus des voiles
        $this->assertStringContainsString('elementsFromPoint', $html);                          // clic à travers le dégradé
    }

    public function test_dark_background_applies_to_the_whole_site(): void
    {
        $html = app(SiteRenderer::class)->html($this->site(['theme' => 'dark']));
        $this->assertStringContainsString('theme-dark', $html);
        $this->assertStringContainsString('.theme-dark body{background:#0b0b10', $html);
        $this->assertStringContainsString('.theme-dark .bg-white{', $html);
    }

    public function test_images_can_be_shown_whole_and_pdf_documents_uploaded_and_displayed(): void
    {
        Storage::fake('local');
        $site = $this->site(['pages' => [['slug' => 'la-carte', 'title' => 'La carte', 'blocks' => [
            ['type' => 'document', 'title' => 'Notre carte', 'file' => 'https://app.joow.fr/media/x/carte.pdf', 'button' => 'Voir la carte'],
            ['type' => 'text', 'title' => 'Menu du jour', 'body' => 'x', 'image' => 'https://exemple.fr/menu-a4.jpg', 'image_ratio' => 'full'],
        ]]]]);
        $user = User::factory()->create(['email' => 'spazio@exemple.fr']);

        $page = app(SiteRenderer::class)->html($site, false, 'la-carte');
        $this->assertStringContainsString('fa-file-pdf', $page);
        $this->assertStringContainsString('type="application/pdf"', $page);
        $this->assertStringContainsString('h-auto object-contain', $page); // image A4 entière

        $this->actingAs($user)->postJson(route('sites.editor.fit', $site->slug), ['url' => 'https://exemple.fr/s0.jpg', 'fit' => 'contain'])->assertOk();
        $this->assertStringContainsString('class="joow-fit', app(SiteRenderer::class)->html($site->fresh()));

        $pdf = UploadedFile::fake()->create('carte.pdf', 300, 'application/pdf');
        $url = $this->actingAs($user)->post(route('sites.editor.file', $site->slug), ['file' => $pdf], ['Accept' => 'application/json'])->assertOk()->json('url');
        $this->assertStringEndsWith('.pdf', $url);
    }

    public function test_popup_and_announcement_modules_render_on_the_live_site(): void
    {
        $site = $this->site([], ['booking' => true, 'popup' => ['enabled' => true, 'title' => 'Soirée jazz vendredi', 'text' => 'Réservez vite', 'button_label' => 'Réserver', 'button_url' => '#reserver', 'delay' => '3', 'frequency' => 'session'],
            'announce' => ['enabled' => true, 'text' => 'Fermé le 15 août']]);
        $html = app(SiteRenderer::class)->html($site);
        $this->assertStringContainsString('id="joow-pop"', $html);
        $this->assertStringContainsString('Soirée jazz vendredi', $html);
        $this->assertStringContainsString('Fermé le 15 août', $html);
        $this->assertStringNotContainsString('id="joow-pop"', app(SiteRenderer::class)->html($site, true)); // pas dans le Studio

        // Popup expirée : plus affichée
        $site->forceFill(['modules' => ['popup' => ['enabled' => true, 'title' => 'Ancienne offre', 'end' => '2020-01-01']]])->save();
        $this->assertStringNotContainsString('id="joow-pop"', app(SiteRenderer::class)->html($site->fresh()));
    }

    public function test_agent_applies_service_photos_dark_theme_fit_and_popup(): void
    {
        $site = $this->site();
        [$d, $modules, $applied] = app(SiteAgent::class)->apply($site->site_data, $site->modules, $site, [
            ['op' => 'set', 'path' => 'content.services.1.image', 'value' => 'https://exemple.fr/pates.jpg'],
            ['op' => 'style', 'theme' => 'dark'],
            ['op' => 'image_fit', 'path' => 'images.services', 'fit' => 'contain'],
            ['op' => 'module_config', 'key' => 'popup', 'config' => ['title' => 'Nouvelle carte', 'text' => 'Venez goûter', 'button_url' => 'javascript:alert(1)']],
        ]);
        $this->assertSame('https://exemple.fr/pates.jpg', $d['content']['services'][1]['image']);
        $this->assertSame('dark', $d['theme']);
        $this->assertSame('contain', $d['image_fit']['https://exemple.fr/s0.jpg']);
        $this->assertTrue($modules['popup']['enabled']);
        $this->assertSame('Nouvelle carte', $modules['popup']['title']);
        $this->assertArrayNotHasKey('button_url', $modules['popup']); // lien dangereux refusé
        $this->assertCount(4, $applied);
    }

    public function test_agent_never_claims_a_change_that_was_not_applied(): void
    {
        config(['services.gemini.key' => 'test']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'reply' => 'J\'ai changé la photo du service 1 et passé le site en noir.',
            'ops' => [['op' => 'set', 'path' => 'interdit.chemin', 'value' => 'x']],
        ])]]]]]])]);
        $out = app(SiteAgent::class)->run($this->site(), 'change la photo du service 1');
        $this->assertSame([], $out['applied']);
        $this->assertStringContainsString("Je n'ai pas réussi à appliquer", $out['reply']);
        $this->assertStringNotContainsString("J'ai changé", $out['reply']);
    }
}
