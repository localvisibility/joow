<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\LegacyStripe;
use Illuminate\Console\Command;

/** Synchronise les formules historiques (ancien compte Stripe Local Visibility) : formule, statut et factures. */
class SyncLegacyStripe extends Command
{
    protected $signature = 'lv:stripe {site? : slug du site Joow} {--email= : e-mail du client sur l\'ancien Stripe (par défaut : celui du site)} {--all : tous les sites en formule historique}';

    protected $description = 'Rapatrie la formule historique et les factures d\'un client depuis l\'ancien compte Stripe Local Visibility';

    public function handle(LegacyStripe $stripe): int
    {
        if (! $stripe->enabled()) {
            $this->error('STRIPE_LV_SECRET absente du .env (clé de l\'ancien compte Stripe).');

            return self::FAILURE;
        }
        $sites = $this->option('all')
            ? Site::where('hosting_plan', 'historique')->get()
            : Site::where('slug', (string) $this->argument('site'))->get();
        if ($sites->isEmpty()) {
            $this->error('Aucun site à synchroniser.');

            return self::FAILURE;
        }
        foreach ($sites as $site) {
            try {
                $r = $stripe->sync($site, $this->option('email'));
            } catch (\Throwable $e) {
                $r = ['error' => $e->getMessage()];
            }
            if (isset($r['error'])) {
                $this->warn("{$site->slug} : {$r['error']}");

                continue;
            }
            $p = $r['plan'];
            $this->info("{$site->slug} : {$r['invoices']} facture(s)".($p ? " · {$p['label']} {$p['amount']} € / {$p['interval']} · {$p['status']}".($p['period_end'] ? " · prochaine échéance {$p['period_end']}" : '') : ' · aucun abonnement trouvé'));
        }

        return self::SUCCESS;
    }
}
