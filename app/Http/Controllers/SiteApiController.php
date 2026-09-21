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

        $r = Reservation::create([
            'site_id' => $site->id, 'site_slug' => $slug,
            'date' => $data['date'], 'time' => $data['time'].':00', 'covers' => $data['covers'],
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $cfg['auto_confirm'] ? 'confirmed' : 'pending', 'source' => 'website',
        ]);
        SiteStat::bump($slug, 'reservations');
        $this->notify($site, $cfg['notify_email'] ?? null, "Nouvelle réservation — {$site->name}",
            "Réservation {$r->status}\nDate : {$data['date']} à {$data['time']}\nCouverts : {$data['covers']}\nNom : {$data['name']}\nTéléphone : {$data['phone']}\nEmail : ".($data['email'] ?? '—')."\nNotes : ".($data['notes'] ?? '—'));
        if (! empty($data['email'])) {
            $this->sendTo($data['email'], "Votre réservation — {$site->name}", ($cfg['confirmation_message'] ?: 'Merci pour votre réservation.')."\n\nDate : {$data['date']} à {$data['time']}\nCouverts : {$data['covers']}\n\n{$site->name}".(! empty($site->site_data['business']['phone']) ? "\n".$site->site_data['business']['phone'] : ''));
        }

        return response()->json(['ok' => true, 'status' => $r->status, 'message' => $cfg['confirmation_message']]);
    }

    /** Chambres + disponibilité sur une période (module Chambres). */
    public function rooms(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->moduleEnabled('rooms'), 404);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after:from']]);

        $rooms = $site->rooms()->where('active', true)->get();
        $out = $rooms->map(function (Room $r) use ($data) {
            $available = null;
            if (! empty($data['from']) && ! empty($data['to'])) {
                $available = $this->roomFree($r, $data['from'], $data['to']);
            }

            return ['id' => $r->id, 'name' => $r->name, 'capacity' => $r->capacity, 'price_night' => $r->price_night, 'available' => $available];
        });

        return response()->json(['rooms' => $out]);
    }

    /** Demande de séjour. */
    public function stay(Request $request, string $slug)
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
            'email'     => ['nullable', 'email', 'max:160'],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'hp'        => ['nullable', 'string', 'max:0'],
        ]);
        if (! empty($request->input('hp'))) {
            return response()->json(['ok' => true]);
        }

        $room = ! empty($data['room_id']) ? Room::where('site_slug', $slug)->find($data['room_id']) : null;
        if ($room && ! $this->roomFree($room, $data['check_in'], $data['check_out'])) {
            return response()->json(['ok' => false, 'error' => "Cette chambre n'est pas disponible sur ces dates."], 422);
        }
        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        $total = $room && $room->price_night ? round($nights * (float) $room->price_night, 2) : null;

        $bk = RoomBooking::create([
            'site_id' => $site->id, 'site_slug' => $slug, 'room_id' => $room?->id,
            'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'guests' => $data['guests'], 'nights' => $nights, 'total' => $total,
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null, 'notes' => $data['notes'] ?? null,
            'status' => 'pending', 'source' => 'website',
        ]);
        SiteStat::bump($slug, 'bookings');
        $this->notify($site, $cfg['notify_email'] ?? null, "Nouvelle demande de séjour — {$site->name}",
            "Du {$data['check_in']} au {$data['check_out']} ({$nights} nuit(s))\nPersonnes : {$data['guests']}\nChambre : ".($room?->name ?? 'au choix')."\nTotal estimé : ".($total !== null ? $total.' €' : '—')."\nNom : {$data['name']}\nTéléphone : {$data['phone']}\nEmail : ".($data['email'] ?? '—')."\nNotes : ".($data['notes'] ?? '—'));

        return response()->json(['ok' => true, 'nights' => $nights, 'total' => $total, 'id' => $bk->id]);
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
        ]);
        SiteStat::bump($slug, 'bot_chats');

        return response()->json(['reply' => $bot->answer($site, $data['message'], $data['history'] ?? [])]);
    }

    /** Balise de mesure d'audience (anonyme). */
    public function view(string $slug)
    {
        if (Site::where('slug', $slug)->exists()) {
            SiteStat::bump($slug, 'views');
        }

        return response()->json(['ok' => true]);
    }

    private function roomFree(Room $room, string $from, string $to): bool
    {
        $overlapBooking = RoomBooking::where('room_id', $room->id)->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '<', $to)->where('check_out', '>', $from)->exists();
        $overlapBlock = RoomBlock::where('room_id', $room->id)->where('start', '<', $to)->where('end', '>', $from)->exists();

        return ! $overlapBooking && ! $overlapBlock;
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
