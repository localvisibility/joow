<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
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

        $stays = RoomBooking::with('room')->whereIn('site_slug', $slugs)->orderByDesc('check_in')->take(300)->get()
            ->map(fn ($b) => ['id' => $b->id, 'site' => $names[$b->site_slug] ?? $b->site_slug, 'room' => $b->room?->name, 'check_in' => $b->check_in->toDateString(), 'check_out' => $b->check_out->toDateString(), 'nights' => $b->nights, 'guests' => $b->guests, 'total' => $b->total, 'name' => $b->name, 'phone' => $b->phone, 'email' => $b->email, 'notes' => $b->notes, 'status' => $b->status, 'source' => $b->source, 'created_at' => $b->created_at]);

        $today = now()->toDateString();

        return Inertia::render('Reservations', [
            'tables' => $tables,
            'stays'  => $stays,
            'sites'  => $names->map(fn ($n, $s) => ['slug' => $s, 'name' => $n])->values(),
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
            $payUrl = null;
            $pay = $site->module('payment');
            $type = $pay['type'] ?? 'deposit';
            if ($site->moduleEnabled('payment') && in_array($type, ['deposit', 'full'], true) && $booking->total && $booking->payment_status !== 'paid' && $connect->ready($site)) {
                $amount = $type === 'full' ? (float) $booking->total : round((float) $booking->total * max(1, min(100, (float) ($pay['deposit_percent'] ?? 30))) / 100, 2);
                try {
                    $ret = route('public.pay.return', ['kind' => 'deposit', 'site' => $site->slug]);
                    $sess = $connect->checkoutForStay($site, $booking->load('room'), $amount, $ret.'&status=ok', $ret.'&status=cancel');
                    $booking->update(['deposit_amount' => $amount, 'payment_status' => 'pending', 'payment_url' => $sess['url'], 'stripe_session_id' => $sess['id']]);
                    $payUrl = $sess['url'];
                } catch (\Throwable $e) {
                    Log::warning('Acompte Stripe : '.$e->getMessage());
                }
            }
            $notifier->stayConfirmed($site, $booking->fresh()->load('room'), $payUrl);
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
        RoomBooking::create($data + ['site_id' => $site?->id, 'nights' => $nights, 'status' => 'confirmed', 'source' => 'manual']);

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
