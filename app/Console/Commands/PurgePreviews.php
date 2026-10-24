<?php

namespace App\Console\Commands;

use App\Models\GenerationJob;
use App\Models\Lead;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime les aperçus créés sans email ni compte, passé le délai de conservation
 * (services.preview_ttl_days). Les sites rattachés à un email ou payés ne sont jamais touchés.
 */
class PurgePreviews extends Command
{
    protected $signature = 'sites:purge-previews {--days= : Délai en jours (défaut : PREVIEW_TTL_DAYS)} {--dry-run : Lister sans supprimer}';

    protected $description = 'Supprime les aperçus anonymes expirés (sans email ni compte, jamais payés)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('services.preview_ttl_days', 7));
        if ($days < 1) {
            $this->warn('Délai invalide, rien à faire.');

            return self::SUCCESS;
        }

        $sites = Site::whereNull('user_id')->whereNull('owner_email')
            ->whereIn('status', ['preview', 'failed', 'generating'])
            ->whereNull('paid_at')
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        foreach ($sites as $site) {
            $this->line(($this->option('dry-run') ? '[dry-run] ' : '')."{$site->slug} · {$site->status} · créé le {$site->created_at->format('d/m/Y')}");
            if ($this->option('dry-run')) {
                continue;
            }
            $this->remove($site);
        }

        $this->info($sites->count().' aperçu(s) '.($this->option('dry-run') ? 'à supprimer' : 'supprimé(s)')." (plus de $days jours sans email).");
        Log::info("Purge des aperçus : {$sites->count()} site(s) supprimé(s) (> $days jours).");

        return self::SUCCESS;
    }

    private function remove(Site $site): void
    {
        $root = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$site->slug;
        if ($site->slug !== '' && is_dir($root)) {
            File::deleteDirectory($root);
        }
        Storage::disk('local')->deleteDirectory('uploads/'.$site->slug);

        GenerationJob::where('site_slug', $site->slug)->delete();
        Lead::where('site_id', $site->id)->delete();
        $site->delete();
    }
}
