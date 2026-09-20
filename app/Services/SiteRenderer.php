<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Rend le HTML d'un site à partir de son contenu stocké (site_data)
 * et l'écrit sur disque. Utilisé par l'éditeur IA (ré-génération instantanée).
 */
class SiteRenderer
{
    public function render(Site $site): string
    {
        $data = $site->site_data ?? [];
        $content = $data['content'] ?? [];
        $business = $data['business'] ?? [];

        $sector = $site->sector ?: 'service';
        $cfg = config("sectors.$sector") ?? config('sectors.service');
        $accent = $data['accent'] ?? $cfg['color'];
        $modules = $site->modules ?: ['booking' => true];

        $html = View::make('generated.site', [
            'b' => $business,
            'c' => $content,
            'sector' => $sector,
            'label' => $cfg['label'],
            'color' => $accent,
            'icon' => $cfg['icon'],
            'cta' => $cfg['cta'],
            'modules' => $modules,
            'mapsKey' => (string) config('services.google_places.key'),
            'slug' => $site->slug,
        ])->render();

        $dir = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$site->slug;
        File::ensureDirectoryExists($dir, 0755);
        File::put($dir.'/index.html', $html);

        $site->update(['html_content' => $html]);

        return $html;
    }
}
