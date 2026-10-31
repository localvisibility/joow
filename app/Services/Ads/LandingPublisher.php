<?php

namespace App\Services\Ads;

use App\Models\LandingPage;
use App\Services\SiteRenderer;
use Illuminate\Support\Facades\View;

/** Rendu et publication statique des pages d'offre : <site>/o/<path>/index.html (toutes les variantes, tirage côté navigateur). */
class LandingPublisher
{
    public function __construct(private SiteRenderer $renderer) {}

    public function html(LandingPage $lp, bool $preview = false): string
    {
        $site = $lp->site;
        $d = $site->site_data ?? [];
        $cfg = config("sectors.{$site->sector}") ?? config('sectors.service');
        $img = (array) ($d['images'] ?? []);
        $phone = $d['business']['phone'] ?? $site->phone;

        return View::make('generated.landing', [
            'lp' => $lp, 'site' => $site, 'preview' => $preview,
            'name' => $site->name, 'city' => $site->city,
            'color' => $d['accent'] ?? $cfg['color'] ?? '#4f46e5',
            'hero' => $img['hero'] ?? null, 'about' => $img['about'] ?? null,
            'phone' => $phone, 'phoneHref' => $phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : null,
            'rating' => $site->rating, 'reviews' => $site->reviews_count,
            'quotes' => array_slice(array_values(array_filter((array) ($d['business']['reviews'] ?? []), fn ($r) => ! empty($r['text']) && ($r['rating'] ?? 5) >= 4)), 0, 3),
            'api' => 'https://app.joow.fr/api/site/'.$site->slug,
            'siteUrl' => $site->custom_domain && $site->domain_status === 'active' ? 'https://'.$site->custom_domain : 'https://'.$site->slug.'.joow.fr',
            'variants' => array_values((array) $lp->variants),
            'content' => \App\Http\Controllers\AdsController::cleanContent($lp->content, $lp->goal),
        ])->render();
    }

    public function publish(LandingPage $lp): void
    {
        $this->renderer->write($lp->site_slug, $this->html($lp), 'o/'.$lp->path);
        $lp->forceFill(['status' => 'live', 'published_at' => $lp->published_at ?? now()])->save();
    }

    /** Retire la page publiée (pause ou suppression). */
    public function unpublish(LandingPage $lp): void
    {
        $dir = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$lp->site_slug.'/o/'.$lp->path;
        @unlink($dir.'/index.html');
        @unlink($dir.'/.joow-page');
        @rmdir($dir);
    }
}
