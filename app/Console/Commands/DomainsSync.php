<?php

namespace App\Console\Commands;

use App\Services\Domains\DomainManager;
use Illuminate\Console\Command;

/** Domaines personnalisés : vérifie le DNS, relit l'état des certificats, publie la liste pour l'agent hôte. */
class DomainsSync extends Command
{
    protected $signature = 'domains:sync';

    protected $description = 'Synchronise les domaines personnalisés (DNS, certificats, export pour l\'agent nginx)';

    public function handle(DomainManager $dm): int
    {
        $r = $dm->sync();
        $this->info("Domaines : {$r['checked']} vérifié(s), {$r['activated']} activé(s).");

        return self::SUCCESS;
    }
}
