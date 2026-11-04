<?php

namespace App\Console\Commands;

use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\PlacesPhoto;
use App\Services\SiteRenderer;
use Illuminate\Console\Command;

/** Retire la clé Google de toutes les données existantes (sites, générations) et republie les sites en ligne. */
class SecureGooglePhotos extends Command
{
    protected $signature = 'places:secure {--dry-run}';

    protected $description = 'Remplace les adresses de photos Google contenant la clé API par des adresses Joow (/gphoto) et republie';

    public function handle(SiteRenderer $renderer): int
    {
        $dry = (bool) $this->option('dry-run');
        $sites = 0;
        Site::query()->select(['id'])->chunkById(200, function ($chunk) use (&$sites, $dry, $renderer) {
            foreach ($chunk as $row) {
                $site = Site::find($row->id);
                $raw = json_encode($site->site_data).(string) $site->html_content;
                if (! str_contains($raw, 'places.googleapis.com')) {
                    continue;
                }
                $sites++;
                $this->line(($dry ? '[dry-run] ' : '').$site->slug);
                if ($dry) {
                    continue;
                }
                $site->site_data = PlacesPhoto::scrub($site->site_data);
                $site->save();
                if (in_array($site->status, ['paid', 'published'], true)) {
                    try {
                        $renderer->publish($site->fresh());
                    } catch (\Throwable $e) {
                        $this->warn("  republication impossible : {$e->getMessage()}");
                    }
                } elseif (str_contains((string) $site->html_content, 'places.googleapis.com')) {
                    $site->forceFill(['html_content' => PlacesPhoto::scrubString((string) $site->html_content)])->saveQuietly();
                }
            }
        });
        $jobs = 0;
        GenerationJob::query()->select(['id'])->chunkById(500, function ($chunk) use (&$jobs, $dry) {
            foreach ($chunk as $row) {
                $j = GenerationJob::find($row->id);
                if (! str_contains((string) json_encode($j->result), 'places.googleapis.com')) {
                    continue;
                }
                $jobs++;
                if (! $dry) {
                    $j->result = PlacesPhoto::scrub($j->result);
                    $j->save();
                }
            }
        });
        $this->info("$sites site(s) et $jobs génération(s) ".($dry ? 'à nettoyer' : 'nettoyés').'.');

        return self::SUCCESS;
    }
}
