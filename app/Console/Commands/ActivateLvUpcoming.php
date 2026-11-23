<?php

namespace App\Console\Commands;

use App\Models\RoomBooking;
use Illuminate\Console\Command;

/**
 * Séjours à venir repris de l'ancien calendrier (statut « Historique ») : ils redeviennent des
 * réservations confirmées, qui ferment leurs dates sur le site. Par défaut seulement ceux que l'ancien
 * calendrier marquait comme confirmés ; --all pour tous les séjours à venir non annulés.
 */
class ActivateLvUpcoming extends Command
{
    protected $signature = 'lv:activate {site : slug du site Joow} {--all : aussi les demandes sans statut confirmé} {--dry-run}';

    protected $description = 'Transforme les séjours à venir de l\'ancien calendrier en réservations confirmées';

    public function handle(): int
    {
        $q = RoomBooking::with('room')->where('site_slug', (string) $this->argument('site'))->where('status', 'archived')
            ->whereDate('check_out', '>=', now()->toDateString())->orderBy('check_in');
        $all = $q->get();
        $rows = $this->option('all') ? $all : $all->filter(fn ($b) => preg_match('/statut : (confirm|accept|valid|pay|book)/i', (string) $b->notes));
        $this->info($all->count().' séjour(s) à venir dans l\'historique · '.$rows->count().' à confirmer'.($this->option('all') ? '' : ' (statut confirmé sur l\'ancien calendrier ; --all pour tous)'));

        $done = 0;
        foreach ($rows as $b) {
            $clash = $b->room_id && RoomBooking::where('room_id', $b->room_id)->whereIn('status', ['pending', 'confirmed'])->whereKeyNot($b->id)
                ->whereDate('check_in', '<', $b->check_out)->whereDate('check_out', '>', $b->check_in)->exists();
            $this->line('  '.$b->check_in->format('d/m/Y').' → '.$b->check_out->format('d/m/Y').' · '.($b->room?->name ?? 'chambre ?').' · '.$b->name.($clash ? '  ⚠ chevauche une réservation existante (laissé tel quel)' : ''));
            if ($clash || ! $b->room_id) {
                continue;
            }
            if (! $this->option('dry-run')) {
                $b->update(['status' => 'confirmed']);
            }
            $done++;
        }
        $this->info($this->option('dry-run') ? "Simulation : $done réservation(s) seraient confirmées." : "$done réservation(s) confirmée(s) : leurs dates sont fermées sur le site.");

        return self::SUCCESS;
    }
}
