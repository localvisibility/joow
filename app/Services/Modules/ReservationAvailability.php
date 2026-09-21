<?php

namespace App\Services\Modules;

use App\Models\Reservation;
use App\Models\Site;
use Carbon\Carbon;

/**
 * Calcule les créneaux disponibles d'un restaurant pour une date donnée,
 * d'après la configuration du module (services par jour, durée de créneau,
 * capacité, délais) et les réservations existantes.
 */
class ReservationAvailability
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Configuration par défaut (fusionnée avec site.modules.restaurant). */
    public static function defaults(): array
    {
        $day = ['closed' => false, 'lunch' => ['12:00', '14:00'], 'dinner' => ['19:00', '22:00']];

        return [
            'enabled'              => false,
            'slot_minutes'         => 30,     // pas entre deux créneaux proposés
            'max_covers_per_slot'  => 20,
            'max_party'            => 10,
            'min_advance_hours'    => 2,
            'max_advance_days'     => 30,
            'auto_confirm'         => true,
            'require_email'        => false,
            'sms_confirm'          => true,   // SMS de confirmation (si Brevo configuré)
            'sms_reminder'         => true,   // SMS de rappel la veille
            'notify_email'         => null,
            'confirmation_message' => 'Merci ! Votre réservation est bien enregistrée. À très vite.',
            'hours'                => array_combine(self::DAYS, array_fill(0, 7, $day)),
        ];
    }

    public static function config(Site $site): array
    {
        $cfg = array_replace_recursive(self::defaults(), $site->module('restaurant'));
        $cfg['hours'] = array_replace(self::defaults()['hours'], $cfg['hours'] ?? []);

        return $cfg;
    }

    /** @return array{slots: array<int, array{time:string, left:int}>, closed: bool, reason: ?string} */
    public function slots(Site $site, string $date, int $covers = 2): array
    {
        $cfg = self::config($site);
        $d = Carbon::parse($date)->startOfDay();
        $now = now();

        if ($d->lt($now->copy()->startOfDay())) {
            return ['slots' => [], 'closed' => true, 'reason' => 'Date passée'];
        }
        if ($d->gt($now->copy()->addDays((int) $cfg['max_advance_days']))) {
            return ['slots' => [], 'closed' => true, 'reason' => 'Trop loin dans le futur'];
        }

        $dayKey = self::DAYS[$d->dayOfWeekIso - 1];
        $day = $cfg['hours'][$dayKey] ?? ['closed' => true];
        if (! empty($day['closed'])) {
            return ['slots' => [], 'closed' => true, 'reason' => 'Fermé ce jour'];
        }

        // Couverts déjà réservés par créneau (statuts actifs)
        $taken = Reservation::where('site_slug', $site->slug)
            ->whereDate('date', $d->toDateString())
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->get()
            ->groupBy(fn ($r) => substr((string) $r->time, 0, 5))
            ->map(fn ($g) => (int) $g->sum('covers'));

        $minStart = $now->copy()->addHours((int) $cfg['min_advance_hours']);
        $step = max(15, (int) $cfg['slot_minutes']);
        $slots = [];

        foreach (['lunch', 'dinner'] as $svc) {
            $range = $day[$svc] ?? null;
            if (! is_array($range) || count($range) < 2 || ! $range[0] || ! $range[1]) {
                continue;
            }
            $t = Carbon::parse($d->toDateString().' '.$range[0]);
            $end = Carbon::parse($d->toDateString().' '.$range[1]);
            while ($t->lte($end)) {
                $hm = $t->format('H:i');
                $left = (int) $cfg['max_covers_per_slot'] - ($taken[$hm] ?? 0);
                if ($t->gte($minStart) && $left >= $covers) {
                    $slots[] = ['time' => $hm, 'left' => $left, 'service' => $svc];
                }
                $t->addMinutes($step);
            }
        }

        return ['slots' => $slots, 'closed' => false, 'reason' => null];
    }
}
