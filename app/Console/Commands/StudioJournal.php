<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\Site;
use Illuminate\Console\Command;

/** Historique des demandes faites à l'assistant IA du Studio pour un site (diagnostic : demandé / appliqué). */
class StudioJournal extends Command
{
    protected $signature = 'studio:journal {search : slug ou partie du nom du site} {--limit=60}';

    protected $description = 'Affiche les demandes faites à l\'IA du Studio et ce qui a réellement été appliqué';

    public function handle(): int
    {
        $q = (string) $this->argument('search');
        $sites = Site::where('slug', $q)->orWhere('slug', 'like', "%$q%")->orWhere('name', 'like', "%$q%")->get(['id', 'slug', 'name', 'site_data', 'modules', 'status']);
        if ($sites->isEmpty()) {
            $this->error('Aucun site trouvé.');

            return self::FAILURE;
        }
        foreach ($sites as $site) {
            $this->line("\n=== {$site->name} ({$site->slug}) · {$site->status} · thème ".($site->site_data['theme'] ?? '-'));
            foreach (array_slice($site->site_data['chat'] ?? [], -(int) $this->option('limit')) as $m) {
                $who = ($m['role'] ?? '') === 'user' ? 'CLIENT' : 'IA    ';
                $this->line(($m['at'] ?? '').' '.$who.' : '.str_replace("\n", ' ', (string) ($m['text'] ?? '')));
                if (($m['role'] ?? '') !== 'user') {
                    $this->line('                     appliqué : '.(($m['applied'] ?? []) ? implode(' | ', (array) $m['applied']) : 'RIEN'));
                }
            }
            $tx = CreditTransaction::where('site_id', $site->id)->where('reason', 'ai_action')->orderBy('created_at')->get();
            $this->line('--- '.$tx->count().' demande(s) IA facturée(s) ou tracée(s), dont '.$tx->filter(fn ($t) => empty(((array) $t->meta)['applied'] ?? []))->count().' sans aucun changement');
        }

        return self::SUCCESS;
    }
}
