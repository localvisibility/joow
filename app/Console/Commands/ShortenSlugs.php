<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sites dont l'adresse <slug>.joow.fr dépasse la limite DNS (63 caractères par label) :
 * l'aperçu est introuvable. Donne-leur un identifiant court, met à jour toutes les tables
 * liées, déplace le dossier publié et republie. Les photos (/media/<ancien>/…) ne bougent pas.
 */
class ShortenSlugs extends Command
{
    protected $signature = 'sites:shorten-slugs {--dry-run : affiche sans modifier}';

    protected $description = 'Raccourcit les identifiants de site trop longs pour un sous-domaine';

    private const TABLES = ['domain_requests', 'generation_jobs', 'invoices', 'leads', 'menu_items', 'reservations', 'room_blocks', 'room_bookings', 'rooms', 'site_stats', 'credit_transactions'];

    public function handle(SiteRenderer $renderer): int
    {
        $sites = Site::whereRaw('length(slug) > 63')->get();
        $this->info($sites->count().' site(s) avec une adresse trop longue.');
        $root = rtrim(config('services.sites_path', '/var/www/sites'), '/');

        foreach ($sites as $site) {
            $old = $site->slug;
            $new = Site::makeSlug($site->name ?: $old);
            $this->line("  $old\n    → $new");
            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($site, $old, $new) {
                $host = fn ($v) => is_string($v) ? str_replace($old.'.joow.fr', $new.'.joow.fr', $v) : $v;
                $site->forceFill([
                    'slug' => $new,
                    'preview_url' => $host($site->preview_url),
                    'live_url' => $host($site->live_url),
                    'subdomain' => $site->subdomain === $old ? $new : $site->subdomain,
                    'site_data' => array_merge(json_decode($host(json_encode($site->site_data ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true) ?: [], [
                        'old_slugs' => array_values(array_unique(array_merge((array) ($site->site_data['old_slugs'] ?? []), [$old]))),
                    ]),
                ])->save();
                foreach (self::TABLES as $t) {
                    if (Schema::hasTable($t) && Schema::hasColumn($t, 'site_slug')) {
                        DB::table($t)->where('site_slug', $old)->update(['site_slug' => $new]);
                    }
                }
            });

            if (is_dir("$root/$old") && ! file_exists("$root/$new")) {
                @rename("$root/$old", "$root/$new");
            }
            try {
                $renderer->publish($site->fresh());
            } catch (\Throwable $e) {
                $this->warn("    republication : ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
