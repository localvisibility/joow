<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Models\SiteStat;
use App\Services\Modules\BotAnswer;
use App\Services\Modules\ReservationAvailability;
use App\Services\Modules\StayConfirmation;
use App\Services\Modules\StayPlanner;
use App\Services\Notifier;
use App\Services\Payments\StripeConnect;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * API publique consommée par les widgets des sites générés (CORS, /api/site/*).
 */
class SiteApiController extends Controller
{
    /** Créneaux disponibles (module Réservation Restaurant). */
    public function availability(Request $request, string $slug, ReservationAvailability $avail)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('restaurant'), 404);
        $data = $request->validate(['date' => ['required', 'date'], 'covers' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return response()->json($avail->slots($site, $data['date'], (int) ($data['covers'] ?? 2)));
    }

    /** Création d'une réservation de table. */
    public function reserve(Request $request, string $slug, ReservationAvailability $avail)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('restaurant'), 404);
        $cfg = ReservationAvailability::config($site);

        $data = $request->validate([
            'date'   => ['required', 'date'],
            'time'   => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'covers' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) $cfg['max_party'])],
            'name'   => ['required', 'string', 'max:120'],
            'phone'  => ['required', 'string', 'max:40'],
            'email'  => [$cfg['require_email'] ? 'required' : 'nullable', 'email', 'max:160'],
            'notes'  => ['nullable', 'string', 'max:1000'],
            'hp'     => ['nullable', 'string', 'max:0'],
        ]);
        if (! empty($request->input('hp'))) {
            return response()->json(['ok' => true]);
        }

        // Vérifie que le créneau est toujours disponible pour ce nombre de couverts
        $slots = $avail->slots($site, $data['date'], (int) $data['covers']);
        $ok = collect($slots['slots'])->firstWhere('time', $data['time']);
        if (! $ok) {
            return response()->json(['ok' => false, 'error' => "Ce créneau n'est plus disponible. Choisissez-en un autre."], 422);
        }

        // Empreinte bancaire anti no-show (module Paiement, type "hold", compte Stripe connecté)
        $pay = $site->module('payment');
        $connect = app(StripeConnect::class);
        $holdPer = (float) ($pay['hold_per_cover'] ?? 0);
        $hold = $site->moduleEnabled('payment') && ($pay['type'] ?? '') === 'hold' && $holdPer > 0 && $connect->ready($site);

        $r = Reservation::create([
            'site_id' => $site->id, 'site_slug' => $slug,
            'date' => $data['date'], 'time' => $data['time'].':00', 'covers' => $data['covers'],
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $hold ? 'pending' : ($cfg['auto_confirm'] ? 'confirmed' : 'pending'),
            'payment_status' => $hold ? 'pending' : 'none',
            'hold_amount' => $hold ? round($holdPer * (int) $data['covers'], 2) : null,
            'source' => 'website',
        ]);

        if ($hold) {
            try {
                $ret = route('public.pay.return', ['kind' => 'hold', 'site' => $slug]);
                $sess = $connect->checkoutForHold($site, $r, (float) $r->hold_amount, $ret.'&status=ok', $ret.'&status=cancel');
                $r->update(['stripe_session_id' => $sess['id']]);

                // La réservation sera confirmée (et notifiée) par le webhook une fois l'empreinte enregistrée.
                return response()->json(['ok' => true, 'status' => 'payment', 'pay_url' => $sess['url'], 'hold_amount' => $r->hold_amount]);
            } catch (\Throwable $e) {
                Log::warning('Empreinte Stripe : '.$e->getMessage());
                $r->update(['payment_status' => 'none', 'hold_amount' => null, 'status' => $cfg['auto_confirm'] ? 'confirmed' : 'pending']);
            }
        }

        SiteStat::bump($slug, 'reservations');
        app(Notifier::class)->reservationCreated($site, $r, $cfg);

        return response()->json(['ok' => true, 'status' => $r->status, 'message' => $cfg['confirmation_message']]);
    }

    /** Chambres + disponibilité sur une période (module Chambres). */
    public function rooms(Request $request, string $slug, StayPlanner $planner)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('rooms'), 404);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after:from'], 'guests' => ['nullable', 'integer', 'min:1', 'max:30']]);
        $guests = (int) ($data['guests'] ?? 2);

        $rooms = $site->rooms()->where('active', true)->get();
        $out = $rooms->map(function (Room $r) use ($data, $planner, $guests) {
            $row = ['id' => $r->id, 'name' => $r->name, 'capacity' => $r->capacity, 'price_night' => $r->price_night, 'from_price' => $planner->fromPrice($r), 'min_nights' => $r->min_nights ?: 1, 'available' => null];
            if (! empty($data['from']) && ! empty($data['to'])) {
                $q = $planner->quote($r, $data['from'], $data['to'], $guests);
                $row['available'] = $planner->free($r, $data['from'], $data['to']);
                $row['bookable'] = $row['available'] && $q['ok'];
                $row['error'] = ! $row['available'] ? 'Indisponible à ces dates' : ($q['ok'] ? null : $q['error']);
                $row['total'] = $q['ok'] ? $q['total'] : null;
                $row['lines'] = $q['ok'] ? $q['lines'] : [];
                $row['nights'] = $q['nights'] ?? null;
            }

            return $row;
        });

        return response()->json(['rooms' => $out]);
    }

    /** Calendrier des disponibilités et prix par nuit (une chambre, ou toutes). */
    public function calendar(Request $request, string $slug, StayPlanner $planner)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('rooms'), 404);
        $data = $request->validate(['from' => ['nullable', 'date'], 'days' => ['nullable', 'integer', 'min:1', 'max:120'], 'room' => ['nullable', 'uuid']]);
        $rooms = $site->rooms()->where('active', true)->when($data['room'] ?? null, fn ($q, $id) => $q->where('id', $id))->get();

        return response()->json(['days' => $planner->calendar($rooms, $data['from'] ?? now()->toDateString(), (int) ($data['days'] ?? 62))]);
    }

    /** Nom de domaine disponible au nom de l'établissement (affiché avant la mise en ligne). */
    public function domain(string $slug, \App\Services\Domains\DomainSuggest $suggest)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        if ($site->custom_domain || in_array($site->status, ['paid', 'published'], true)) {
            return response()->json(['domain' => null]);
        }

        return response()->json($suggest->forSite($site) ?? ['domain' => null]);
    }

    /** Demande de séjour (ou réservation instantanée si le propriétaire l'a activée). */
    public function stay(Request $request, string $slug, StayPlanner $planner)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('rooms'), 404);
        $cfg = $site->module('rooms');

        $data = $request->validate([
            'check_in'  => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests'    => ['required', 'integer', 'min:1', 'max:30'],
            'room_id'   => ['nullable', 'uuid'],
            'name'      => ['required', 'string', 'max:120'],
            'phone'     => ['required', 'string', 'max:40'],
            'email'     => [! empty($cfg['instant']) ? 'required' : 'nullable', 'email', 'max:160'],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'hp'        => ['nullable', 'string', 'max:0'],
        ]);
        if (! empty($request->input('hp'))) {
            return response()->json(['ok' => true]);
        }

        $rooms = $site->rooms()->where('active', true)->get();
        if (! empty($data['room_id'])) {
            $room = $rooms->firstWhere('id', $data['room_id']);
            abort_unless($room, 422, 'Chambre inconnue.');
            $candidates = collect([$room]);
        } else {
            $candidates = $rooms;
        }

        // Chambre choisie, ou la moins chère qui respecte les règles et est libre
        $chosen = null;
        $quote = null;
        $firstError = null;
        foreach ($candidates as $r) {
            $q = $planner->quote($r, $data['check_in'], $data['check_out'], (int) $data['guests']);
            if (! $q['ok']) {
                $firstError ??= $q['error'];
                continue;
            }
            if (! $planner->free($r, $data['check_in'], $data['check_out'])) {
                $firstError ??= $candidates->count() > 1 ? 'Aucune chambre n\'est disponible sur ces dates.' : 'Cette chambre n\'est pas disponible sur ces dates.';
                continue;
            }
            if (! $chosen || ($q['total'] ?? INF) < ($quote['total'] ?? INF)) {
                [$chosen, $quote] = [$r, $q];
            }
        }
        if ($rooms->isNotEmpty() && ! $chosen) {
            return response()->json(['ok' => false, 'error' => $firstError ?: 'Aucune chambre n\'est disponible sur ces dates.'], 422);
        }

        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        $instant = ! empty($cfg['instant']) && $chosen;
        $bk = RoomBooking::create([
            'site_id' => $site->id, 'site_slug' => $slug, 'room_id' => $chosen?->id,
            'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'guests' => $data['guests'], 'nights' => $nights,
            'total' => $quote['total'] ?? null,
            'breakdown' => $quote ? ['lines' => $quote['lines'], 'nightly' => array_map(fn ($n) => ['date' => $n['date'], 'price' => $n['price']], $quote['nightly'])] : null,
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null, 'notes' => $data['notes'] ?? null,
            'status' => $instant ? 'confirmed' : 'pending', 'source' => 'website',
        ]);
        SiteStat::bump($slug, 'bookings');

        $payUrl = null;
        if ($instant) {
            app(Notifier::class)->stayRequested($site, $bk->load('room'), $cfg, ownerOnly: true);
            $payUrl = app(StayConfirmation::class)->confirm($site, $bk);
        } else {
            app(Notifier::class)->stayRequested($site, $bk->load('room'), $cfg);
        }

        return response()->json([
            'ok' => true, 'id' => $bk->id, 'status' => $bk->status, 'nights' => $nights, 'total' => $bk->total !== null ? (float) $bk->total : null,
            'room' => $chosen?->name, 'lines' => $quote['lines'] ?? [], 'pay_url' => $payUrl,
        ]);
    }

    /** Assistant IA. */
    public function bot(Request $request, string $slug, BotAnswer $bot)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('bot'), 404);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:500'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required', 'in:user,ai'],
            'history.*.text' => ['required', 'string', 'max:800'],
            'page' => ['nullable', 'string', 'max:200'],
        ]);
        SiteStat::bump($slug, 'bot_chats');

        return response()->json(['reply' => $bot->answer($site, $data['message'], $data['history'] ?? [], $data['page'] ?? '')]);
    }

    /** Balise de mesure d'audience (anonyme). */
    public function view(Request $request, string $slug)
    {
        if ($site = Site::where('slug', $slug)->first()) {
            SiteStat::bump($slug, 'views');
            // Avant paiement, les visites viennent surtout du prospect : site ouvert en plein écran
            if ($request->boolean('full') && ! in_array($site->status, ['paid', 'published'], true)) {
                $site->track('fullpage');
            }
        }

        return response()->json(['ok' => true]);
    }

    private function notify(Site $site, ?string $to, string $subject, string $body): void
    {
        $to = $to ?: ($site->owner_email ?: $site->email);
        if ($to) {
            $this->sendTo($to, $subject, $body);
        }
    }

    private function sendTo(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            Log::warning('Mail failed: '.$e->getMessage());
        }
    }
}
