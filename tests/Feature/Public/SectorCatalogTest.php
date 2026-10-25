<?php

namespace Tests\Feature\Public;

use App\Services\SectorDetector;
use Tests\TestCase;

class SectorCatalogTest extends TestCase
{
    public function test_catalog_entries_are_complete(): void
    {
        $sectors = config('sectors');
        $this->assertGreaterThanOrEqual(73, count($sectors));
        foreach ($sectors as $key => $cfg) {
            foreach (['label', 'color', 'icon', 'cta', 'font', 'theme', 'hero', 'order', 'modules', 'keywords', 'brief', 'stock'] as $f) {
                $this->assertArrayHasKey($f, $cfg, "$key.$f");
            }
            $this->assertGreaterThanOrEqual(3, count($cfg['stock']), "$key.stock");
        }
    }

    /** Détection par mots-clés (IA désactivée en test) : nom + type principal Google. */
    public function test_keyword_detection_covers_new_trades(): void
    {
        $d = app(SectorDetector::class);
        $this->assertSame('agence_web', $d->detect('Pixel Studio', ['website_designer', 'point_of_interest']));
        $this->assertSame('agence_web', $d->detect('Green Mandarin Design · création de sites internet', []));
        $this->assertSame('graphiste', $d->detect('Atelier Graphik', ['graphic_designer']));
        $this->assertSame('communication', $d->detect('Agence Lumen', ['marketing_agency']));
        $this->assertSame('developpeur', $d->detect('Nova Software', ['software_company']));
        $this->assertSame('coach', $d->detect('Marie Dupont coach de vie', []));
        $this->assertSame('rh', $d->detect('Talents & Co', ['employment_agency']));
        $this->assertSame('voyage', $d->detect('Évasion Voyages', ['travel_agency']));
        $this->assertSame('animaux', $d->detect('Au Poil Toilettage', ['pet_groomer']));
        $this->assertSame('pressing', $d->detect('Pressing de la Gare', ['dry_cleaner']));
        $this->assertSame('securite', $d->detect('Vigil Sécurité Privée', ['security_guard_service']));
        $this->assertSame('imprimerie', $d->detect('Imprimerie Moderne', ['print_shop']));
        $this->assertSame('boucherie', $d->detect('Boucherie Zorglub', ['butcher_shop']));
        $this->assertSame('boucherie', $d->detect('Poissonnerie de la Criée', []));
        $this->assertSame('primeur', $d->detect('Au Panier Frais', ['grocery_store']));
        $this->assertSame('librairie', $d->detect('Librairie des Halles', ['book_store']));
        $this->assertSame('cycles', $d->detect('Cycles Martin', ['bicycle_store']));
        $this->assertSame('maison', $d->detect('Déco & Co', ['furniture_store']));
    }

    public function test_forms_exist_for_new_trades(): void
    {
        $forms = config('forms');
        $this->assertSame('Votre projet', $forms['agence_web']['steps'][0]['title']);
        $this->assertSame($forms['conseil'], $forms['coach']);
        $this->assertArrayHasKey('rh', $forms);
    }
}
