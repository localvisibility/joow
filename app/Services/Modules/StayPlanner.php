<?php

namespace App\Services\Modules;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Séjours : prix nuit par nuit (saisons, week-end), durée de séjour, frais, disponibilités.
 *
 * Une « nuit » est désignée par sa date d'arrivée : la nuit du 14 juillet va du 14 au 15.
 * Week-end = nuits du vendredi et du samedi. Une saison couvre des dates MM-JJ incluses et
 * peut chevaucher le nouvel an (ex. 12-20 → 01-05).
 */
class StayPlanner
{
    public const MAX_DAYS = 550;

    /** Saison qui s'applique à une nuit, ou null. */
    public function season(Room $room, CarbonInterface $night): ?array
    {
        $md = $night->format('m-d');
        foreach ((array) ($room->seasons ?? []) as $s) {
            $from = (string) ($s['from'] ?? '');
            $to = (string) ($s['to'] ?? '');
            if (! preg_match('/^\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{2}-\d{2}$/', $to)) {
                continue;
            }
            $in = $from <= $to ? ($md >= $from && $md <= $to) : ($md >= $from || $md <= $to);
            if ($in) {
                return $s;
            }
        }

        return null;
    }

    public function isWeekend(CarbonInterface $night): bool
    {
        return in_array($night->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true);
    }

    /** Prix d'une nuit et règles qui s'y appliquent. */
    public function night(Room $room, CarbonInterface $night): array
    {
        $season = $this->season($room, $night);
        $we = $this->isWeekend($night);
        $num = fn ($v) => $v === null || $v === '' ? null : (float) $v;

        $price = null;
        if ($season) {
            $price = ($we ? $num($season['weekend_price'] ?? null) : null) ?? $num($season['price'] ?? null);
        }
        $price ??= ($we ? $num($room->weekend_price) : null) ?? $num($room->price_night);

        return [
            'date' => $night->toDateString(),
            'price' => $price,
            'season' => $season['name'] ?? null,
            'weekend' => $we,
            'min_nights' => max(1, (int) (($season['min_nights'] ?? null) ?: ($room->min_nights ?: 1))),
        ];
    }

    /**
     * Devis complet d'un séjour. ok=false avec un message clair si une règle n'est pas respectée
     * (durée minimale / maximale, capacité). Ne vérifie pas les disponibilités (voir free()).
     */
    public function quote(Room $room, string $checkIn, string $checkOut, int $guests = 2, bool $enforce = true): array
    {
        $in = Carbon::parse($checkIn)->startOfDay();
        $out = Carbon::parse($checkOut)->startOfDay();
        $n = (int) $in->diffInDays($out, false);
        if ($n < 1) {
            return ['ok' => false, 'error' => 'La date de départ doit suivre la date d\'arrivée.'];
        }
        if ($n > self::MAX_DAYS) {
            return ['ok' => false, 'error' => 'Séjour trop long.'];
        }

        $nights = [];
        for ($d = $in->copy(); $d < $out; $d->addDay()) {
            $nights[] = $this->night($room, $d);
        }
        $min = $nights[0]['min_nights'];
        $max = $room->max_nights ?: null;
        $base = ['nights' => $n, 'min_nights' => $min, 'max_nights' => $max];

        // $enforce = false : saisie par le propriétaire, prix calculé sans imposer les règles
        if ($enforce && $n < $min) {
            return $base + ['ok' => false, 'error' => "Séjour de {$min} nuits minimum pour « {$room->name} » à ces dates."];
        }
        if ($enforce && $max && $n > $max) {
            return $base + ['ok' => false, 'error' => "Séjour de {$max} nuits maximum pour « {$room->name} »."];
        }
        if ($enforce && $room->capacity && $guests > $room->capacity) {
            return $base + ['ok' => false, 'error' => "« {$room->name} » accueille {$room->capacity} personne".($room->capacity > 1 ? 's' : '').' maximum.'];
        }

        $priced = ! in_array(null, array_column($nights, 'price'), true);
        $lodging = $priced ? round(array_sum(array_column($nights, 'price')), 2) : null;
        $extraGuests = $room->included_guests && $room->extra_guest_fee ? max(0, $guests - $room->included_guests) : 0;
        $extra = $extraGuests ? round($extraGuests * (float) $room->extra_guest_fee * $n, 2) : 0.0;
        $cleaning = (float) ($room->cleaning_fee ?? 0);
        $tax = $room->tourist_tax ? round((float) $room->tourist_tax * $guests * $n, 2) : 0.0;

        $lines = [];
        if ($priced) {
            foreach ($this->groupNights($nights) as $g) {
                $lines[] = ['label' => $g['count'].' nuit'.($g['count'] > 1 ? 's' : '').' × '.$this->eur($g['price']).($g['season'] ? ' ('.$g['season'].')' : ($g['weekend'] ? ' (week-end)' : '')), 'amount' => round($g['count'] * $g['price'], 2)];
            }
        }
        if ($extra > 0) {
            $lines[] = ['label' => "Personne supplémentaire × {$extraGuests}", 'amount' => $extra];
        }
        if ($cleaning > 0) {
            $lines[] = ['label' => 'Frais de ménage', 'amount' => round($cleaning, 2)];
        }
        if ($tax > 0) {
            $lines[] = ['label' => 'Taxe de séjour', 'amount' => $tax];
        }

        return $base + [
            'ok' => true,
            'nightly' => $nights,
            'lodging' => $lodging,
            'extra_guests' => $extra,
            'cleaning' => $cleaning,
            'tax' => $tax,
            'lines' => $lines,
            'total' => $priced ? round($lodging + $extra + $cleaning + $tax, 2) : null,
        ];
    }

    /** Regroupe les nuits consécutives au même prix pour un détail lisible. */
    private function groupNights(array $nights): array
    {
        $out = [];
        foreach ($nights as $n) {
            $k = $n['price'].'|'.$n['season'].'|'.($n['season'] ? '' : (int) $n['weekend']);
            if (isset($out[$k])) {
                $out[$k]['count']++;
            } else {
                $out[$k] = ['price' => $n['price'], 'season' => $n['season'], 'weekend' => $n['weekend'], 'count' => 1];
            }
        }

        return array_values($out);
    }

    private function eur(float $v): string
    {
        return number_format($v, $v == floor($v) ? 0 : 2, ',', ' ').' €';
    }

    /** La chambre est-elle libre pour toutes les nuits [checkIn, checkOut[ ? */
    public function free(Room $room, string $checkIn, string $checkOut, ?string $ignoreBooking = null): bool
    {
        $booked = RoomBooking::where('room_id', $room->id)->whereIn('status', ['pending', 'confirmed'])
            ->when($ignoreBooking, fn ($q) => $q->where('id', '!=', $ignoreBooking))
            ->where('check_in', '<', $checkOut)->where('check_out', '>', $checkIn)->exists();
        if ($booked) {
            return false;
        }

        return ! RoomBlock::where('site_slug', $room->site_slug)
            ->where(fn ($q) => $q->where('room_id', $room->id)->orWhereNull('room_id'))
            ->where('start', '<', $checkOut)->where('end', '>', $checkIn)->exists();
    }

    /** Nuits occupées (date => true) d'une chambre entre deux dates. */
    public function busyNights(Room $room, CarbonInterface $from, CarbonInterface $to): array
    {
        $busy = [];
        $mark = function ($start, $end) use (&$busy, $from, $to) {
            $d = Carbon::parse($start)->max($from)->copy()->startOfDay();
            $e = Carbon::parse($end)->min($to)->copy();
            for (; $d < $e; $d->addDay()) {
                $busy[$d->toDateString()] = true;
            }
        };
        RoomBooking::where('room_id', $room->id)->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '<', $to->toDateString())->where('check_out', '>', $from->toDateString())
            ->get(['check_in', 'check_out'])->each(fn ($b) => $mark($b->check_in, $b->check_out));
        RoomBlock::where('site_slug', $room->site_slug)->where(fn ($q) => $q->where('room_id', $room->id)->orWhereNull('room_id'))
            ->where('start', '<', $to->toDateString())->where('end', '>', $from->toDateString())
            ->get(['start', 'end'])->each(fn ($b) => $mark($b->start, $b->end));

        return $busy;
    }

    /**
     * Calendrier public : pour chaque nuit, disponibilité, prix et durée minimale.
     * Sans chambre précise, une nuit est libre si au moins une chambre l'est (prix = le plus bas).
     *
     * @param  Collection<int, Room>  $rooms
     */
    public function calendar(Collection $rooms, string $from, int $days): array
    {
        $start = Carbon::parse($from)->startOfDay()->max(now()->startOfDay());
        $days = max(1, min(120, $days));
        $end = $start->copy()->addDays($days);
        $busy = $rooms->mapWithKeys(fn (Room $r) => [$r->id => $this->busyNights($r, $start, $end)]);

        $out = [];
        for ($d = $start->copy(); $d < $end; $d->addDay()) {
            $k = $d->toDateString();
            $best = null;
            foreach ($rooms as $r) {
                if (! empty($busy[$r->id][$k])) {
                    continue;
                }
                $n = $this->night($r, $d);
                if ($best === null || ($n['price'] !== null && ($best['price'] === null || $n['price'] < $best['price']))) {
                    $best = $n;
                }
            }
            $out[] = ['d' => $k, 'a' => $best !== null, 'p' => $best['price'] ?? null, 'm' => $best['min_nights'] ?? null];
        }

        return $out;
    }

    /** Prix d'appel « à partir de » : le plus bas des tarifs de la chambre. */
    public function fromPrice(Room $room): ?float
    {
        $prices = array_filter([
            $room->price_night, $room->weekend_price,
            ...array_map(fn ($s) => $s['price'] ?? null, (array) ($room->seasons ?? [])),
            ...array_map(fn ($s) => $s['weekend_price'] ?? null, (array) ($room->seasons ?? [])),
        ], fn ($v) => $v !== null && $v !== '' && (float) $v > 0);

        return $prices ? (float) min(array_map('floatval', $prices)) : null;
    }
}
