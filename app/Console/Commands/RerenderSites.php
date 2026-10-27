<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Console\Command;

/** Régénère les fichiers HTML de tous les sites (après une évolution du gabarit). */
class RerenderSites extends Command
{
    protected $signature = 'sites:rerender {--status=preview,paid,published : Statuts concernés} {--slug= : Un seul site}';

    protected $description = 'Réécrit les pages de tous les sites générés avec le gabarit courant';

    public function handle(SiteRenderer $renderer): int
    {
        $q = Site::query()->whereNotNull('site_data');
        $this->option('slug') ? $q->where('slug', $this->option('slug')) : $q->whereIn('status', explode(',', (string) $this->option('status')));
        $ok = $ko = 0;
        foreach ($q->cursor() as $site) {
            if (empty($site->site_data['content'])) {
                continue;
            }
            try {
                $renderer->writeAll($site->slug, $renderer->renderAll($site));
                $ok++;
            } catch (\Throwable $e) {
                $ko++;
                $this->warn($site->slug.' : '.$e->getMessage());
            }
        }
        $this->info("Sites réécrits : $ok, en erreur : $ko");

        return self::SUCCESS;
    }
}
