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

    public int $timeout = 240;

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
            // Image par service : l'IA désigne la photo (1..N) qui illustre chaque prestation
            $usedImgs = [];
            foreach ($content['services'] ?? [] as $i => $s) {
                $pi = (int) ($s['photo'] ?? 0) - 1;
                $url = $cur['photos'][$pi]['url'] ?? null;
                // Une photo n'illustre qu'un seul service (sinon la même image se répète sur plusieurs cartes)
                $content['services'][$i]['image'] = ($url && ! in_array($url, $usedImgs, true)) ? $url : null;
                if ($content['services'][$i]['image']) {
                    $usedImgs[] = $url;
                }
                unset($content['services'][$i]['photo']);
            }

            // 2b) Refonte : les pages internes du site existant deviennent des pages Joow (une fois)
            $pages = $prev['pages'] ?? null;
            if ($b['source'] === 'refonte' && empty($pages) && count($b['existing']['pages'] ?? []) > 1) {
                $progress(['substep' => 'pages']);
                $pages = app(\App\Services\PageImporter::class)->build($b, $content, $cur['photos'], $cfg['label']);
                $progress(['pages' => array_map(fn ($p) => $p['title'], $pages)]);
            }

            // 3) Modules par défaut du métier (première génération seulement) et images choisies
            $modules = $this->site->modules ?: ($cfg['modules'] ?? ['booking' => true]);
            $images = array_filter($prev['images'] ?? []) ?: array_filter($cur['images'], fn ($v) => $v !== null && $v !== []);

            // Snapshot structuré (source de vérité pour l'éditeur) — on préserve
            // les réglages existants (couleur, images, sections) lors d'une régénération.
            $siteData = array_merge($prev, [
                'content'     => array_merge($content, Arr::only($prev['content'] ?? [], ['sections'])),
                'business'    => $b,
                'images'      => $images,
                'photos_meta' => $cur['photos'],
                'accent'      => $prev['accent'] ?? $cfg['color'],
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
                    app(\App\Services\Notifier::class)->editLink($this->site, $to);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Email site prêt : '.$e->getMessage());
                }
            }

            $job?->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => array_merge($job?->fresh()->result ?? [], ['step' => 'done', 'bytes' => strlen($html), 'url' => 'https://'.$this->site->slug.'.joow.fr', 'timing' => ['photos' => $tPhotos, 'content' => $tContent, 'total' => round(microtime(true) - $t0, 1)]]),
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
