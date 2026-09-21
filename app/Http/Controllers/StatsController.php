<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Reservation;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Models\SiteStat;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Espace client : statistiques (visites, demandes, réservations). */
class StatsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $sitesQ = $user->isAdmin()
            ? Site::query()
            : Site::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email));
        $sites = $sitesQ->orderByDesc('created_at')->get(['slug', 'name']);
        $slug = $request->query('site');
        $slugs = $slug && $sites->contains('slug', $slug) ? collect([$slug]) : $sites->pluck('slug');

        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = SiteStat::whereIn('site_slug', $slugs)->where('day', '>=', $from->toDateString())->get()->groupBy(fn ($r) => $r->day->toDateString());
        $series = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $k = $d->toDateString();
            $g = $rows->get($k, collect());
            $series[] = ['day' => $k, 'views' => (int) $g->sum('views'), 'leads' => (int) $g->sum('leads'), 'reservations' => (int) $g->sum('reservations') + (int) $g->sum('bookings'), 'bot' => (int) $g->sum('bot_chats')];
        }

        $sum = fn ($k) => array_sum(array_column($series, $k));
        $leadsTotal = Lead::whereIn('site_slug', $slugs)->where('created_at', '>=', $from)->count();
        $resTotal = Reservation::whereIn('site_slug', $slugs)->where('created_at', '>=', $from)->count() + RoomBooking::whereIn('site_slug', $slugs)->where('created_at', '>=', $from)->count();
        $views = $sum('views');

        return Inertia::render('Stats', [
            'sites'   => $sites,
            'current' => $slug,
            'days'    => $days,
            'series'  => $series,
            'kpi'     => [
                'views'        => $views,
                'leads'        => max($leadsTotal, $sum('leads')),
                'reservations' => max($resTotal, $sum('reservations')),
                'bot'          => $sum('bot'),
                'conversion'   => $views > 0 ? round((max($leadsTotal, $sum('leads')) + max($resTotal, $sum('reservations'))) / $views * 100, 1) : 0,
            ],
            'recent'  => Reservation::whereIn('site_slug', $slugs)->latest()->take(8)->get(['site_slug', 'date', 'time', 'covers', 'name', 'status'])
                ->map(fn ($r) => ['type' => 'table', 'site' => $r->site_slug, 'label' => $r->name.' · '.$r->covers.' couv.', 'when' => $r->date->format('d/m').' '.substr((string) $r->time, 0, 5), 'status' => $r->status]),
        ]);
    }
}
