<?php

namespace App\Services;

use App\Jobs\PublishSiteJob;
use App\Models\Site;
use App\Services\Pages\PageSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Rendu HTML d'un site à partir de son contenu stocké (site_data), et
 * publication sur disque (dossier servi par nginx hôte).
 *
 * Un site = une page d'accueil (index.html) + des pages additionnelles
 * (site_data.pages → <slug-page>/index.html), qui partagent nav, pied de page,
 * style et modules.
 *
 * L'aperçu de l'éditeur est rendu à la volée depuis la base (same-origin),
 * la publication écrit les fichiers statiques ; si le conteneur web n'a pas
 * les droits d'écriture, la publication est déléguée au worker (queue).
 */
class SiteRenderer
{
    /** Pages additionnelles normalisées. */
    public function pages(Site $site): array
    {
        return PageSchema::normalizePages($site->site_data['pages'] ?? []);
    }

    /**
     * HTML d'une page du site.
     *
     * @param  bool         $editMode  Injecte le runtime d'édition (Studio)
     * @param  string|null  $pageSlug  null = accueil, sinon slug d'une page additionnelle
     */
    public function html(Site $site, bool $editMode = false, ?string $pageSlug = null): string
    {
        $data = $site->site_data ?? [];
        $sector = $site->sector ?: 'service';
        $cfg = config("sectors.$sector") ?? config('sectors.service');

        // Modules : état résolu (activé + config) pour chaque clé du catalogue
        $mods = [];
        foreach (array_keys(config('modules')) as $key) {
            $mods[$key] = $site->module($key) + ['enabled' => $site->moduleEnabled($key)];
        }
        $mods['restaurant'] = \App\Services\Modules\ReservationAvailability::config($site) + ['enabled' => $site->moduleEnabled('restaurant')];
        $mods['bot'] = array_replace(\App\Services\Modules\BotAnswer::defaults(), $site->module('bot')) + ['enabled' => $site->moduleEnabled('bot')];
        // Paiement : "ready" = compte Stripe connecté et capable d'encaisser
        $mods['payment']['ready'] = (bool) config('cashier.secret') && $site->stripe_account_id && $site->stripe_charges_enabled;

        $pages = $this->pages($site);
        $page = null;
        $pi = null;
        if ($pageSlug !== null) {
            foreach ($pages as $i => $p) {
                if ($p['slug'] === $pageSlug) {
                    $page = $p;
                    $pi = $i;
                    break;
                }
            }
            if ($page === null) {
                abort(404);
            }
        }

        return View::make('generated.site', [
            'b'        => $data['business'] ?? [],
            'c'        => $data['content'] ?? [],
            'sector'   => $sector,
            'label'    => $cfg['label'],
            'color'    => $data['accent'] ?? $cfg['color'],
            'font'     => $data['font'] ?? null,
            'icon'     => $cfg['icon'],
            'cta'      => $cfg['cta'],
            'modules'  => $mods,
            'images'   => $data['images'] ?? [],
            'booking'  => $data['booking'] ?? [],
            // Formulaire multi-étapes : personnalisé par le client, sinon modèle du métier
            'form'     => $data['form'] ?? (config("forms.$sector") ?? config('forms.default')),
            'menuItems' => $site->moduleEnabled('menu') ? $site->menuItems()->where('available', true)->get() : collect(),
            'rooms'    => $site->moduleEnabled('rooms') ? $site->rooms()->where('active', true)->get() : collect(),
            'legalHtml' => $site->moduleEnabled('legal') ? app(\App\Services\Modules\LegalGenerator::class)->html($site) : null,
            'mapsKey'  => (string) config('services.google_places.key'),
            'slug'     => $site->slug,
            'editMode' => $editMode,
            // Pages additionnelles + page courante (null = accueil)
            'pages'    => $pages,
            'page'     => $page,
            'pi'       => $pi,
            // Système de design sectoriel (police, thème, mise en page du hero, photos de secours)
            'design'   => [
                'font'  => $cfg['font'] ?? 'Space Grotesk',
                'theme' => $data['theme'] ?? ($cfg['theme'] ?? 'light'),
                'hero'  => $data['hero_style'] ?? ($cfg['hero'] ?? 'editorial'),
                'stock' => $cfg['stock'] ?? [],
            ],
        ])->render();
    }

    /** Rend toutes les pages : ['' => html accueil, 'slug-page' => html…]. */
    public function renderAll(Site $site): array
    {
        $out = ['' => $this->html($site)];
        foreach ($this->pages($site) as $p) {
            $out[$p['slug']] = $this->html($site, false, $p['slug']);
        }

        return $out;
    }

    /** Re-rend et publie le site (accueil + pages). Renvoie ['queued' => bool]. */
    public function publish(Site $site): array
    {
        $all = $this->renderAll($site);
        $site->update(['html_content' => $all['']]);

        try {
            $this->writeAll($site->slug, $all);

            return ['ok' => true, 'queued' => false];
        } catch (\Throwable $e) {
            Log::warning("Publication directe impossible ({$site->slug}) : {$e->getMessage()} — délégué au worker.");
            PublishSiteJob::dispatch($site->id);

            return ['ok' => true, 'queued' => true];
        }
    }

    /** Écrit toutes les pages et supprime les pages retirées. */
    public function writeAll(string $slug, array $all): void
    {
        foreach ($all as $pageSlug => $html) {
            $this->write($slug, $html, $pageSlug ?: null);
        }
        $this->cleanupPages($slug, array_values(array_filter(array_keys($all))));
    }

    /** Écrit index.html (ou <page>/index.html) avec des droits partagés (web + worker). */
    public function write(string $slug, string $html, ?string $pageSlug = null): void
    {
        $root = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$slug;
        $dir = $pageSlug ? $root.'/'.$pageSlug : $root;

        foreach (array_unique([$root, $dir]) as $d) {
            if (! is_dir($d)) {
                @mkdir($d, 0777, true);
            }
            @chmod($d, 0777);
        }

        $tmp = $dir.'/.index.'.uniqid('', true).'.tmp';
        if (@file_put_contents($tmp, $html) === false) {
            throw new \RuntimeException("Écriture impossible dans $dir");
        }
        @chmod($tmp, 0666);

        if (! @rename($tmp, $dir.'/index.html')) {
            @unlink($tmp);
            throw new \RuntimeException("Remplacement impossible de $dir/index.html");
        }
        @chmod($dir.'/index.html', 0666);
        if ($pageSlug) {
            // Marqueur : ce dossier est une page Joow (nettoyable si la page est supprimée)
            @file_put_contents($dir.'/.joow-page', '1');
            @chmod($dir.'/.joow-page', 0666);
        }

        // Worker (root) : aligner le propriétaire sur le serveur web.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            foreach (array_unique([$root, $dir, $dir.'/index.html']) as $p) {
                @chown($p, 'www-data');
                @chgrp($p, 'www-data');
            }
        }
    }

    /** Supprime les dossiers de pages Joow qui n'existent plus. */
    public function cleanupPages(string $slug, array $keep): void
    {
        $root = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$slug;
        if (! is_dir($root)) {
            return;
        }
        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $name = basename($d);
            if (in_array($name, $keep, true) || ! is_file($d.'/.joow-page')) {
                continue;
            }
            @unlink($d.'/index.html');
            @unlink($d.'/.joow-page');
            @rmdir($d);
        }
    }

    /** Compatibilité : ancien point d'entrée. */
    public function render(Site $site): string
    {
        $this->publish($site);

        return (string) $site->html_content;
    }
}
