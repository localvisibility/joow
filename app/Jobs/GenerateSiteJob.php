<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\BusinessSource;
use App\Services\GeminiContent;
use App\Services\PhotoCurator;
use App\Services\SiteRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class GenerateSiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 360;

    public int $tries = 2;

    /** Backoff entre les tentatives (secondes). */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public Site $site,
        public ?string $jobId = null,
    ) {}

    public function handle(BusinessSource $source, GeminiContent $gemini, PhotoCurator $curator): void
    {
        $job = $this->jobId ? GenerationJob::find($this->jobId) : null;
        $job?->update(['status' => 'processing', 'started_at' => now(), 'attempts' => ($job->attempts + 1)]);

        try {
            // Progression détaillée : la page d'attente la lit toutes les 1,5 s (journal « en direct »)
            $progress = fn (array $more) => $job?->update(['result' => array_merge($job->fresh()->result ?? [], $more)]);

            // Fiche d'entreprise normalisée : Google, questionnaire ou site existant (refonte)
            $b = $source->resolve($this->site, $progress);
            $this->site->refresh();

            $sector = $this->site->sector ?: 'service';
            $cfg = config("sectors.$sector") ?? config('sectors.service');
            $prev = $this->site->site_data ?? [];
            $small = fn (string $u) => preg_replace('/maxWidthPx=\d+/', 'maxWidthPx=640', $u);

            // 1) Lecture des photos par l'IA : rôles (hero, à propos, services, galerie) + descriptions
            $t0 = microtime(true);
            $progress([
                'step' => 'photos', 'source' => $b['source'], 'name' => $b['name'], 'city' => $b['city'] ?? null, 'rating' => $b['rating'] ?? null,
                'reviews_count' => $b['reviews_count'] ?? 0, 'sector' => $cfg['label'], 'color' => $cfg['color'], 'font' => $cfg['font'] ?? null,
                'photos' => array_map($small, array_slice(array_values(array_filter($b['photos'] ?? [])), 0, 8)),
            ]);
            $cur = $curator->curate($b['photos'] ?? [], $cfg['label'], $b['name']);
            $progress([
                'photo_notes' => array_values(array_map(fn ($ph) => ['category' => $ph['category'], 'description' => $ph['description'], 'quality' => $ph['quality']], array_slice($cur['photos'], 0, 8))),
                'hero' => isset($cur['images']['hero']) ? $small($cur['images']['hero']) : null,
            ]);
            $descOf = fn (?string $url) => $url ? (collect($cur['photos'])->firstWhere('url', $url)['description'] ?? '') : '';
            $notes = [
                'notes'         => $cur['notes'],
                'hero_desc'     => $descOf($cur['images']['hero'] ?? null),
                'about_desc'    => $descOf($cur['images']['about'] ?? null),
                'services_desc' => $descOf($cur['images']['services'] ?? null),
            ];

            // 2) Rédaction, nourrie par le brief métier et la description des photos
            $tPhotos = round(microtime(true) - $t0, 1);
            $progress(['step' => 'content']);
            $content = $gemini->generate($b, $sector, $cfg['label'], $notes, (string) ($cfg['brief'] ?? ''));
            $progress(['hero_title' => $content['hero_title'] ?? null, 'tagline' => $content['tagline'] ?? null, 'services' => array_values(array_filter(array_map(fn ($s) => $s['name'] ?? null, array_slice($content['services'] ?? [], 0, 6))))]);
            // Image par service : l'IA désigne la photo (1..N) qui illustre chaque prestation.
            // On ne garde que les photos nettes, sans texte, jugées aptes à illustrer une prestation ; une photo par service.
            $usedImgs = [];
            foreach ($content['services'] ?? [] as $i => $s) {
                $pi = (int) ($s['photo'] ?? 0) - 1;
                $ph = $cur['photos'][$pi] ?? null;
                $ok = $ph && ($ph['quality'] ?? 3) >= 3 && empty($ph['text_overlay']) && empty($ph['duplicate']) && (int) ($ph['fit']['service'] ?? 3) >= 2;
                $url = $ok ? $ph['url'] : null;
                $content['services'][$i]['image'] = ($url && ! in_array($url, $usedImgs, true)) ? $url : null;
                if ($content['services'][$i]['image']) {
                    $usedImgs[] = $url;
                }
                unset($content['services'][$i]['photo']);
            }

            // 2c) Sujets sans photo : la bonne image depuis une banque d'images (requêtes IA, choix par vision)
            //     — haut de page, à propos, chaque prestation sans visuel, galerie trop courte.
            $images0 = array_filter($prev['images'] ?? []) ?: array_filter($cur['images'], fn ($v) => $v !== null && $v !== []);
            $slots = [];
            if (empty($images0['hero'])) {
                $slots['hero'] = ['subject' => ($content['hero_title'] ?? '').' — '.$cfg['label'].' à '.($b['city'] ?? 'France'), 'role' => 'hero', 'pick' => true];
            }
            if (empty($images0['about'])) {
                $slots['about'] = ['subject' => ($content['about_title'] ?? 'Qui sommes-nous').' — '.$cfg['label'].' au travail', 'role' => 'about', 'pick' => true];
            }
            foreach ($content['services'] ?? [] as $i => $s) {
                if (empty($s['image']) && ! empty($s['name'])) {
                    $slots["service_$i"] = ['subject' => $s['name'].(! empty($s['desc']) ? ' — '.$s['desc'] : ''), 'role' => 'prestation', 'pick' => $i < 3];
                }
            }
            $galleryNow = array_values((array) ($images0['gallery'] ?? []));
            for ($g = count($galleryNow); $g < 4; $g++) {
                $slots["gallery_$g"] = ['subject' => $cfg['label'].' — '.(['ambiance du lieu', 'geste du métier', 'détail de matière ou produit', 'accueil des clients'][$g] ?? 'ambiance'), 'role' => 'galerie', 'pick' => false];
            }
            $tStock = 0;
            if ($slots) {
                $tS = microtime(true);
                $progress(['substep' => 'stock', 'stock_slots' => count($slots)]);
                $already = array_values(array_filter(array_merge([$images0['hero'] ?? null, $images0['about'] ?? null, $images0['services'] ?? null], $galleryNow, $usedImgs)));
                $filled = app(\App\Services\StockPhotos::class)->fill($slots, $cfg['label'], (string) ($cfg['brief'] ?? ''), $already, $cfg['stock'] ?? []);
                $credits = [];
                foreach ($filled as $key => $pick) {
                    if (str_starts_with($key, 'service_')) {
                        $content['services'][(int) substr($key, 8)]['image'] = $pick['url'];
                    } elseif (str_starts_with($key, 'gallery_')) {
                        $galleryNow[] = $pick['url'];
                    } else {
                        $images0[$key] = $pick['url'];
                    }
                    if ($pick['credit']) {
                        $credits[$pick['url']] = $pick['credit'];
                    }
                }
                $images0['gallery'] = array_values(array_unique($galleryNow));
                if ($credits) {
                    $images0['credits'] = array_merge((array) ($images0['credits'] ?? []), $credits);
                }
                $tStock = round(microtime(true) - $tS, 1);
                $progress(['stock_filled' => count($filled), 'hero' => isset($images0['hero']) ? $small($images0['hero']) : null]);
            }

            // 2b) Refonte : les pages internes du site existant deviennent des pages Joow (une fois)
            $pages = $prev['pages'] ?? null;
            if (empty($pages) && count($b['existing']['pages'] ?? []) > 1) {
                $progress(['substep' => 'pages']);
                $pages = app(\App\Services\PageImporter::class)->build($b, $content, $cur['photos'], $cfg['label']);
                $progress(['pages' => array_map(fn ($p) => $p['title'], $pages)]);
            }

            // 2d) Les photos Google utilisées sont rapatriées chez Joow : plus de facturation à chaque visite,
            //     clé API absente du HTML, chargement plus rapide. Les autres sources restent telles quelles.
            $googleUrls = [];
            $collect = function ($u) use (&$googleUrls) {
                if (is_string($u) && str_contains($u, 'places.googleapis.com')) {
                    $googleUrls[] = $u;
                }
            };
            foreach (['hero', 'about', 'services'] as $k) {
                $collect($images0[$k] ?? null);
            }
            foreach ((array) ($images0['gallery'] ?? []) as $u) {
                $collect($u);
            }
            foreach ($content['services'] ?? [] as $s) {
                $collect($s['image'] ?? null);
            }
            if ($googleUrls) {
                $mirrored = app(\App\Services\ImageStore::class)->mirror(array_values(array_unique($googleUrls)), $this->site->slug, 16);
                $swap = fn ($u) => is_string($u) && isset($mirrored[$u]) ? $mirrored[$u] : $u;
                foreach (['hero', 'about', 'services'] as $k) {
                    if (isset($images0[$k])) {
                        $images0[$k] = $swap($images0[$k]);
                    }
                }
                $images0['gallery'] = array_map($swap, (array) ($images0['gallery'] ?? []));
                foreach ($content['services'] ?? [] as $i => $s) {
                    $content['services'][$i]['image'] = $swap($s['image'] ?? null);
                }
                $b['photos'] = array_map($swap, $b['photos'] ?? []);
                $cur['photos'] = array_map(fn ($ph) => ['url' => $swap($ph['url'])] + $ph, $cur['photos']);
            }

            // 2e) Couleur d'accent de la marque : logo d'abord, puis photo principale (sauf réglage déjà fait dans le Studio)
            $accent = $prev['accent'] ?? null;
            if (! $accent) {
                $accent = app(\App\Services\Palette::class)->fromSources([$b['logo'] ?? null, $images0['hero'] ?? null]) ?: $cfg['color'];
                $progress(['color' => $accent, 'brand_color' => $accent !== $cfg['color']]);
            }

            // 3) Modules par défaut du métier (première génération seulement) et images choisies
            $modules = $this->site->modules ?: ($cfg['modules'] ?? ['booking' => true]);
            $images = array_filter($images0, fn ($v) => $v !== null && $v !== []);

            // Snapshot structuré (source de vérité pour l'éditeur) — on préserve
            // les réglages existants (couleur, images, sections) lors d'une régénération.
            $siteData = array_merge($prev, [
                'content'     => array_merge($content, Arr::only($prev['content'] ?? [], ['sections'])),
                'business'    => $b,
                'images'      => $images,
                'photos_meta' => $cur['photos'],
                'accent'      => $accent,
            ] + ($pages ? ['pages' => $pages] : []));
            $this->site->update(['site_data' => $siteData, 'modules' => $modules]);

            $tContent = round(microtime(true) - $t0 - $tPhotos, 1);
            $progress(['step' => 'render']);
            $renderer = app(SiteRenderer::class);
            $all = $renderer->renderAll($this->site->fresh());
            $html = $all[''] ?? $renderer->html($this->site->fresh());
            $renderer->writeAll($this->site->slug, $all);

            // Généré et visible comme aperçu (statut 'preview') ; devient 'paid' après achat.
            \Illuminate\Support\Facades\Log::info(sprintf('Génération %s : photos %.1fs · contenu %.1fs · total %.1fs', $this->site->slug, $tPhotos, $tContent, microtime(true) - $t0));
            $this->site->update([
                'status'        => 'preview',
                'html_content'  => $html,
                'rating'        => $b['rating'] ?? $this->site->rating,
                'reviews_count' => $b['reviews_count'] ?? $this->site->reviews_count,
                'preview_url'   => 'https://'.$this->site->slug.'.joow.fr',
            ]);

            // Lien privé du Studio envoyé à l'email rattaché (saisi à l'accueil ou pendant l'attente)
            if ($to = $this->site->fresh()->owner_email) {
                try {
                    $accounts = app(\App\Services\Accounts::class);
                    [$user] = $accounts->ensure($to, [$this->site->slug], $this->site->source === 'questionnaire' ? 'intake' : ($this->site->source === 'refonte' ? 'refonte' : 'site'));
                    $accounts->welcome($user, $this->site->fresh());
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Email site prêt : '.$e->getMessage());
                }
            }

            $job?->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => array_merge($job?->fresh()->result ?? [], ['step' => 'done', 'bytes' => strlen($html), 'url' => 'https://'.$this->site->slug.'.joow.fr', 'timing' => ['photos' => $tPhotos, 'content' => round($tContent - $tStock, 1), 'stock' => $tStock, 'total' => round(microtime(true) - $t0, 1)]]),
            ]);
        } catch (\Throwable $e) {
            // Dernière tentative épuisée => statut 'failed' (évite un site 'preview' cassé).
            if ($this->attempts() >= $this->tries) {
                $this->site->update(['status' => 'failed']);
            }
            $job?->update(['status' => 'failed', 'error' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }

    /** Appelé quand toutes les tentatives ont échoué. */
    public function failed(\Throwable $e): void
    {
        $this->site->update(['status' => 'failed']);
        if ($this->jobId) {
            GenerationJob::where('id', $this->jobId)->update([
                'status' => 'failed', 'error' => $e->getMessage(), 'completed_at' => now(),
            ]);
        }
    }
}
