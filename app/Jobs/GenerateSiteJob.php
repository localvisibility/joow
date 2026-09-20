<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\GeminiContent;
use App\Services\GooglePlaces;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

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

    public function handle(GooglePlaces $places, GeminiContent $gemini): void
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

            $content = $gemini->generate($b, $sector, $cfg['label']);
            $modules = $this->site->modules ?: ['booking' => true];
            $accent = $this->site->site_data['accent'] ?? $cfg['color'];

            $html = View::make('generated.site', [
                'b' => $b,
                'c' => $content,
                'sector' => $sector,
                'label' => $cfg['label'],
                'color' => $accent,
                'icon' => $cfg['icon'],
                'cta' => $cfg['cta'],
                'modules' => $modules,
                'mapsKey' => (string) config('services.google_places.key'),
                'slug' => $this->site->slug,
            ])->render();

            $dir = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$this->site->slug;
            File::ensureDirectoryExists($dir, 0755);
            File::put($dir.'/index.html', $html);

            // Généré et visible comme aperçu (statut 'preview') ; devient 'paid' après achat.
            $this->site->update([
                'status'        => 'preview',
                'html_content'  => $html,
                'rating'        => $b['rating'] ?? $this->site->rating,
                'reviews_count' => $b['reviews_count'] ?? $this->site->reviews_count,
                'preview_url'   => 'https://'.$this->site->slug.'.joow.fr',
                'modules'       => $modules,
                // Snapshot pour ré-édition (éditeur IA) sans re-solliciter Google.
                'site_data'     => ['content' => $content, 'business' => $b, 'accent' => $accent],
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
