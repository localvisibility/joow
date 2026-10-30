<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alerte en temps réel aux administrateurs quand un prospect est « chaud » (Studio ouvert,
 * modifications / assistant IA, paiement ouvert), pour l'appeler pendant qu'il est dessus.
 * Une alerte par site et par type d'événement et par jour au plus.
 */
class HotLeads
{
    public const EVENTS = [
        'studio' => ['✏️', 'a ouvert le Studio'],
        'edit' => ['🛠', 'modifie son site'],
        'checkout' => ['💳', 'a ouvert la page de paiement'],
    ];

    public function notify(Site $site, string $event): void
    {
        $admins = array_filter((array) config('joow.admins'));
        if (! $admins || ! isset(self::EVENTS[$event]) || in_array($site->status, ['paid', 'published'], true)) {
            return;
        }
        if (! Cache::add("joow:hot:{$site->id}:$event:".now()->toDateString(), 1, now()->endOfDay())) {
            return;
        }
        [$icon, $what] = self::EVENTS[$event];
        $act = (array) ($site->site_data['activity'] ?? []);
        $ai = collect((array) ($site->site_data['chat'] ?? []))->where('role', 'user')->count();
        $lines = array_filter([
            "$icon {$site->name} $what (à l'instant).",
            '',
            'Email : '.($site->owner_email ?: 'pas encore laissé'),
            $site->phone ? 'Téléphone : '.$site->phone : null,
            $site->city ? 'Ville : '.$site->city : null,
            'Métier : '.(config("sectors.{$site->sector}.label") ?? $site->sector),
            'Activité : '.collect(['preview' => 'aperçu', 'fullpage' => 'plein écran', 'studio' => 'Studio', 'edit' => 'modifications'])->filter(fn ($l, $k) => ! empty($act[$k]))->map(fn ($l, $k) => $l.' ×'.$act[$k]['n'])->implode(', ').($ai ? ", assistant IA ×$ai" : ''),
            '',
            'Voir son site : https://'.$site->slug.'.joow.fr',
            'Back-office : '.route('admin.index', ['tab' => 'sites', 'q' => $site->slug]),
            '',
            $site->phone ? '👉 C\'est le bon moment pour appeler.' : '👉 C\'est le bon moment pour lui écrire depuis le back-office.',
        ], fn ($l) => $l !== null);

        foreach ($admins as $to) {
            try {
                Mail::raw(implode("\n", $lines), fn ($m) => $m->to($to)->subject("🔥 $icon {$site->name} $what"));
            } catch (\Throwable $e) {
                Log::info('Alerte prospect : '.$e->getMessage());
            }
        }
    }
}
