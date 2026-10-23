<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\GeminiContent;
use App\Services\GooglePlaces;
use App\Services\PhotoCurator;
use App\Services\SiteRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class GenerateSiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

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

    public function handle(GooglePlaces $places, GeminiContent $gemini, PhotoCurator $curator): void
    {
        $job = $this->jobId ? GenerationJob::find($this->jobId) : null;
        $job?->update(['status' => 'processing', 'started_at' => now(), 'attempts' => ($job->attempts + 1)]);

        try {
            $b = $places->lookup($this->site->place_id);
            if (! $b) {
                throw new \RuntimeException('Fiche Google introuvable pour place_id '.$this->site->place_id);
            }

            $sector = $this->site->sector ?: 'service';
            $cfg = config("sectors.$sector") ?? config('sectors.service');
            $prev = $this->site->site_data ?? [];

            // 1) Lecture des photos par l'IA : rôles (hero, à propos, services, galerie) + descriptions
            $job?->update(['result' => ['step' => 'photos']]);
            $cur = $curator->curate($b['photos'] ?? [], $cfg['label'], $b['name']);
            $descOf = fn (?string $url) => $url ? (collect($cur['photos'])->firstWhere('url', $url)['description'] ?? '') : '';
            $notes = [
                'notes'         => $cur['notes'],
                'hero_desc'     => $descOf($cur['images']['hero'] ?? null),
                'about_desc'    => $descOf($cur['images']['about'] ?? null),
                'services_desc' => $descOf($cur['images']['services'] ?? null),
            ];

            // 2) Rédaction, nourrie par le brief métier et la description des photos
            $job?->update(['result' => ['step' => 'content']]);
            $content = $gemini->generate($b, $sector, $cfg['label'], $notes, (string) ($cfg['brief'] ?? ''));
            // Image par service : l'IA désigne la photo (1..N) qui illustre chaque prestation
            foreach ($content['services'] ?? [] as $i => $s) {
                $pi = (int) ($s['photo'] ?? 0) - 1;
                $content['services'][$i]['image'] = $cur['photos'][$pi]['url'] ?? null;
                unset($content['services'][$i]['photo']);
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
            ]);
            $this->site->update(['site_data' => $siteData, 'modules' => $modules]);

            $renderer = app(SiteRenderer::class);
            $html = $renderer->html($this->site->fresh());
            $renderer->write($this->site->slug, $html);

            // Généré et visible comme aperçu (statut 'preview') ; devient 'paid' après achat.
            $this->site->update([
                'status'        => 'preview',
                'html_content'  => $html,
                'rating'        => $b['rating'] ?? $this->site->rating,
                'reviews_count' => $b['reviews_count'] ?? $this->site->reviews_count,
                'preview_url'   => 'https://'.$this->site->slug.'.joow.fr',
            ]);

            $job?->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => ['bytes' => strlen($html), 'url' => 'https://'.$this->site->slug.'.joow.fr'],
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
