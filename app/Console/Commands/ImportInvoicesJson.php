<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Importe les factures existantes (fichiers JSON d'OVH /data/invoices/invoice-*.json)
 * vers la table `invoices`. Idempotent (upsert par invoice_number).
 *
 *   php artisan import:invoices {path=/var/www/import/invoices}
 */
class ImportInvoicesJson extends Command
{
    protected $signature = 'import:invoices {path : dossier contenant les invoice-*.json}
        {--dry-run}';

    protected $description = 'Importe les factures JSON existantes vers la table invoices';

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');
        $files = glob($path.'/invoice-*.json');

        if ($files === false || count($files) === 0) {
            $this->error("Aucun fichier invoice-*.json trouvé dans $path");
            return self::FAILURE;
        }
        $this->line('Fichiers trouvés : '.count($files));

        $dry = (bool) $this->option('dry-run');
        $ok = 0;

        foreach ($files as $file) {
            $d = json_decode(@file_get_contents($file), true);
            if (! $d || empty($d['invoice_id'])) {
                $this->warn('Ignoré (JSON invalide) : '.basename($file));
                continue;
            }

            $year = date('Y', strtotime($d['created_at'] ?? 'now'));
            $number = $d['invoice_number'] ?? ('FAC-'.$year.'-'.str_pad((string) $d['invoice_id'], 5, '0', STR_PAD_LEFT));

            if ($dry) { $ok++; continue; }

            Invoice::updateOrCreate(
                ['invoice_number' => $number],
                [
                    'site_slug'    => $d['site_slug'] ?? null,
                    'client_email' => $d['client_email'] ?? '',
                    'client_name'  => $d['client_name'] ?? null,
                    'module_name'  => $d['module_name'] ?? 'Module',
                    'price_ht'     => $d['price_ht'] ?? 0,
                    'tva_rate'     => $d['tva_rate'] ?? 20,
                    'currency'     => $d['currency'] ?? 'EUR',
                    'payment_id'   => $d['payment_id'] ?? null,
                    'status'       => 'paid',
                    'issued_at'    => $d['created_at'] ?? now(),
                ]
            );
            $ok++;
        }

        $this->info("Factures importées : $ok".($dry ? ' (simulé)' : ''));
        return self::SUCCESS;
    }
}
