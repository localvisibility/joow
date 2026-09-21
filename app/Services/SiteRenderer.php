<?php

namespace App\Services;

use App\Jobs\PublishSiteJob;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Rendu HTML d'un site à partir de son contenu stocké (site_data), et
 * publication sur disque (dossier servi par nginx hôte).
 *
 * L'aperçu de l'éditeur est rendu à la volée depuis la base (same-origin),
 * la publication écrit le fichier statique ; si le conteneur web n'a pas
 * les droits d'écriture, la publication est déléguée au worker (queue).
 */
class SiteRenderer
{
    /** HTML du site (éditable si $editMode : runtime d'édition injecté). */
    public function html(Site $site, bool $editMode = false): string
    {
        $data = $site->site_data ?? [];
        $sector = $site->sector ?: 'service';
        $cfg = config("sectors.$sector") ?? config('sectors.service');

        return View::make('generated.site', [
            'b'        => $data['business'] ?? [],
            'c'        => $data['content'] ?? [],
            'sector'   => $sector,
            'label'    => $cfg['label'],
            'color'    => $data['accent'] ?? $cfg['color'],
            'font'     => $data['font'] ?? null,
            'icon'     => $cfg['icon'],
            'cta'      => $cfg['cta'],
            'modules'  => $site->modules ?: ['booking' => true],
            'images'   => $data['images'] ?? [],
            'booking'  => $data['booking'] ?? [],
            'mapsKey'  => (string) config('services.google_places.key'),
            'slug'     => $site->slug,
            'editMode' => $editMode,
        ])->render();
    }

    /** Re-rend et publie le site. Renvoie ['queued' => bool]. */
    public function publish(Site $site): array
    {
        $html = $this->html($site);
        $site->update(['html_content' => $html]);

        try {
            $this->write($site->slug, $html);

            return ['ok' => true, 'queued' => false];
        } catch (\Throwable $e) {
            Log::warning("Publication directe impossible ({$site->slug}) : {$e->getMessage()} — délégué au worker.");
            PublishSiteJob::dispatch($site->id);

            return ['ok' => true, 'queued' => true];
        }
    }

    /** Écrit index.html avec des droits partagés (web + worker). */
    public function write(string $slug, string $html): void
    {
        $dir = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$slug;

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);

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

        // Worker (root) : aligner le propriétaire sur le serveur web.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($dir, 'www-data');
            @chgrp($dir, 'www-data');
            @chown($dir.'/index.html', 'www-data');
            @chgrp($dir.'/index.html', 'www-data');
        }
    }

    /** Compatibilité : ancien point d'entrée. */
    public function render(Site $site): string
    {
        $this->publish($site);

        return (string) $site->html_content;
    }
}
