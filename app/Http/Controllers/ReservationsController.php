<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Services\Modules\ReservationAvailability;
use App\Services\Notifier;
use App\Services\Payments\StripeConnect;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Espace client : réservations de table et demandes de séjour.
 */
class ReservationsController extends Controller
{
    public function index(Request $request)
    {
        $slugs = $this->slugs($request);
        $names = Site::whereIn('slug', $slugs)->pluck('name', 'slug');

        $tables = Reservation::whereIn('site_slug', $slugs)->orderByDesc('date')->orderByDesc('time')->take(300)->get()
            ->map(fn ($r) => ['id' => $r->id, 'site' => $names[$r->site_slug] ?? $r->site_slug, 'date' => $r->date->toDateString(), 'time' => substr((string) $r->time, 0, 5), 'covers' => $r->covers, 'name' => $r->name, 'phone' => $r->phone, 'email' => $r->email, 'notes' => $r->notes, 'status' => $r->status, 'source' => $r->source, 'created_at' => $r->created_at]);

        $stays = RoomBooking::with('room')->whereIn('site_slug', $slugs)->orderByDesc('check_in')->take(1000)->get()
            ->map(fn ($b) => ['id' => $b->id, 'site' => $names[$b->site_slug] ?? $b->site_slug, 'room' => $b->room?->name, 'check_in' => $b->check_in->toDateString(), 'check_out' => $b->check_out->toDateString(), 'nights' => $b->nights, 'guests' => $b->guests, 'total' => $b->total, 'name' => $b->name, 'phone' => $b->phone, 'email' => $b->email, 'notes' => $b->notes, 'status' => $b->status, 'source' => $b->source, 'created_at' => $b->created_at]);

        $today = now()->toDateString();

        return Inertia::render('Reservations', [
            'tables' => $tables,
            'stays'  => $stays,
            'sites'  => $names->map(fn ($n, $s) => ['slug' => $s, 'name' => $n])->values(),
            'rooms'  => Room::whereIn('site_slug', $slugs)->orderBy('sort_order')->get(['id', 'name', 'site_slug']),
            // Dates fermées à venir (ajoutées à la main ou reprises de l'ancien calendrier) ; les flux iCal sont gérés par la plateforme d'origine
            'blocks' => RoomBlock::whereIn('site_slug', $slugs)->where('end', '>', now()->toDateString())->orderBy('start')->take(500)->get()
                ->map(fn ($b) => ['id' => $b->id, 'site' => $names[$b->site_slug] ?? $b->site_slug, 'room_id' => $b->room_id, 'start' => Carbon::parse($b->start)->toDateString(), 'end' => Carbon::parse($b->end)->toDateString(), 'summary' => $b->summary, 'source' => $b->source]),
            'kpi'    => [
                'today'   => $tables->where('date', $today)->whereIn('status', ['confirmed', 'pending', 'seated'])->sum('covers'),
                'pending' => $tables->where('status', 'pending')->count() + $stays->where('status', 'pending')->count(),
                'arrivals' => $stays->where('check_in', $today)->where('status', 'confirmed')->count(),
            ],
        ]);
    }

    public function updateTable(Request $request, Reservation $reservation, Notifier $notifier, StripeConnect $connect)
    {
        $this->authorizeSlug($request, $reservation->site_slug);
        $status = $request->validate(['status' => ['required', 'in:pending,confirmed,seated,cancelled,noshow']])['status'];
        $prev = $reservation->status;
        $reservation->update(['status' => $status]);

        // Empreinte bancaire : débitée en cas de no-show, libérée sinon
        if ($reservation->stripe_payment_intent_id && $reservation->payment_status === 'authorized') {
            try {
                if ($status === 'noshow') {
                    $connect->capture($reservation->stripe_payment_intent_id);
                    $reservation->update(['payment_status' => 'captured']);
                } elseif (in_array($status, ['seated', 'cancelled'], true)) {
                    $connect->release($reservation->stripe_payment_intent_id);
                    $reservation->update(['payment_status' => 'released']);
                }
            } catch (\Throwable $e) {
                Log::warning('Empreinte : '.$e->getMessage());
            }
        }

        if ($prev !== $status && in_array($status, ['confirmed', 'cancelled'], true) && ($site = Site::where('slug', $reservation->site_slug)->first())) {
            $notifier->reservationStatus($site, $reservation, ReservationAvailability::config($site));
        }

        return back();
    }

    public function updateStay(Request $request, RoomBooking $booking, Notifier $notifier, StripeConnect $connect)
    {
        $this->authorizeSlug($request, $booking->site_slug);
        $status = $request->validate(['status' => ['required', 'in:pending,confirmed,cancelled']])['status'];
        $prev = $booking->status;
        $booking->update(['status' => $status]);
        $site = Site::where('slug', $booking->site_slug)->first();
        if (! $site || $prev === $status) {
            return back();
        }

        if ($status === 'confirmed') {
            app(\App\Services\Modules\StayConfirmation::class)->confirm($site, $booking);
        } elseif ($status === 'cancelled') {
            $notifier->stayCancelled($site, $booking->load('room'));
        }

        return back();
    }

    /** Ajout manuel (téléphone, sur place). */
    public function storeTable(Request $request)
    {
        $data = $request->validate([
            'site_slug' => ['required', 'string'], 'date' => ['required', 'date'], 'time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'covers' => ['required', 'integer', 'min:1', 'max:50'], 'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->authorizeSlug($request, $data['site_slug']);
        $site = Site::where('slug', $data['site_slug'])->first();
        Reservation::create($data + ['site_id' => $site?->id, 'time' => $data['time'].':00', 'status' => 'confirmed', 'source' => 'manual']);

        return back();
    }

    public function storeStay(Request $request)
    {
        $data = $request->validate([
            'site_slug' => ['required', 'string'], 'check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:30'], 'room_id' => ['nullable', 'uuid'], 'total' => ['nullable', 'numeric'],
            'name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->authorizeSlug($request, $data['site_slug']);
        $site = Site::where('slug', $data['site_slug'])->first();
        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        $room = ! empty($data['room_id']) ? \App\Models\Room::where('site_slug', $data['site_slug'])->find($data['room_id']) : null;
        if (! $room && Room::where('site_slug', $data['site_slug'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['room_id' => 'Choisissez la chambre : ses dates seront fermées sur le site.']);
        }
        if ($room && ($data['total'] ?? null) === null) {
            // Saisie au téléphone : prix calculé avec les tarifs de la chambre (règles de durée non imposées)
            $q = app(\App\Services\Modules\StayPlanner::class)->quote($room, $data['check_in'], $data['check_out'], (int) $data['guests'], enforce: false);
            if ($q['ok'] && $q['total'] !== null) {
                $data['total'] = $q['total'];
                $data['breakdown'] = ['lines' => $q['lines']];
            }
        }
        RoomBooking::create($data + ['site_id' => $site?->id, 'nights' => $nights, 'status' => 'confirmed', 'source' => 'manual']);

        return back();
    }

    /** Import Excel / CSV : analyse (dry) puis enregistrement des réservations confirmées. */
    public function import(Request $request, \App\Services\Modules\StayImport $importer)
    {
        $data = $request->validate([
            'site_slug' => ['required', 'string'], 'file' => ['required', 'file', 'max:8192'],
            'room_id' => ['nullable', 'uuid'], 'dry' => ['nullable', 'boolean'],
        ]);
        if (! in_array(strtolower($request->file('file')->getClientOriginalExtension()), ['xlsx', 'csv', 'txt'], true)) {
            return response()->json(['error' => 'Format accepté : Excel (.xlsx) ou CSV. Pour un ancien fichier .xls, ouvrez-le et enregistrez-le en .xlsx.'], 422);
        }
        $this->authorizeSlug($request, $data['site_slug']);
        $site = Site::where('slug', $data['site_slug'])->firstOrFail();
        try {
            $rows = $importer->read($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'Fichier illisible : enregistrez-le en Excel (.xlsx) ou en CSV.'], 422);
        }
        $analysis = $importer->analyse($site, $rows, $data['room_id'] ?? null);
        if ($request->boolean('dry')) {
            return response()->json($analysis);
        }

        return response()->json(['imported' => $importer->import($site, $analysis)] + $analysis);
    }

    /** Fermer des dates (une chambre ou tout l'établissement) : plus aucune demande possible sur ces nuits. */
    public function storeBlock(Request $request)
    {
        $data = $request->validate([
            'site_slug' => ['required', 'string'], 'room_id' => ['nullable', 'uuid'],
            'start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start'], 'summary' => ['nullable', 'string', 'max:120'],
        ], ['end.after_or_equal' => 'La date de fin doit être après la date de début.']);
        $this->authorizeSlug($request, $data['site_slug']);
        if (! empty($data['room_id'])) {
            abort_unless(Room::where('site_slug', $data['site_slug'])->whereKey($data['room_id'])->exists(), 422, 'Chambre inconnue.');
        }
        // Saisie « du … au … inclus » : la fin du blocage est le lendemain de la dernière nuit fermée
        RoomBlock::create([
            'site_slug' => $data['site_slug'], 'room_id' => ($data['room_id'] ?? null) ?: null, 'source' => 'manual',
            'start' => $data['start'], 'end' => Carbon::parse($data['end'])->addDay()->toDateString(), 'summary' => ($data['summary'] ?? null) ?: 'Fermé',
        ]);

        return back();
    }

    public function destroyBlock(Request $request, RoomBlock $block)
    {
        $this->authorizeSlug($request, $block->site_slug);
        abort_unless($block->source === 'manual', 422, 'Ces dates viennent d\'un calendrier synchronisé (Airbnb, Booking…) : libérez-les sur la plateforme d\'origine.');
        $block->delete();

        return back();
    }

    private function slugs(Request $request)
    {
        $user = $request->user();

        return $user->isAdmin()
            ? Site::pluck('slug')
            : Site::where('user_id', $user->id)->orWhere('owner_email', $user->email)->pluck('slug');
    }

    private function authorizeSlug(Request $request, string $slug): void
    {
        abort_unless($this->slugs($request)->contains($slug), 403);
    }
}
