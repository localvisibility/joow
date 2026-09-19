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

            $html = View::make('generated.site', [
                'b' => $b,
                'c' => $content,
                'sector' => $sector,
                'label' => $cfg['label'],
                'color' => $cfg['color'],
                'icon' => $cfg['icon'],
                'cta' => $cfg['cta'],
                'mapsKey' => (string) config('services.google_places.key'),
                'slug' => $this->site->slug,
            ])->render();

            $dir = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$this->site->slug;
            File::ensureDirectoryExists($dir, 0755);
            File::put($dir.'/index.html', $html);

            $this->site->update([
                'status'        => 'published',
                'html_content'  => $html,
                'rating'        => $b['rating'] ?? $this->site->rating,
                'reviews_count' => $b['reviews_count'] ?? $this->site->reviews_count,
                'published_at'  => now(),
                'live_url'      => 'https://'.$this->site->slug.'.joow.fr',
            ]);

            $job?->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => ['bytes' => strlen($html), 'url' => 'https://'.$this->site->slug.'.joow.fr'],
            ]);
        } catch (\Throwable $e) {
            $this->site->update(['status' => 'preview']);
            $job?->update(['status' => 'failed', 'error' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }
}
