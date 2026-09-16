<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // TODO Phase 2 : scoping par locataire (sites du client connecté).
        // Pour l'instant on affiche l'ensemble du parc (vue opérateur).
        $stats = [
            'sites'     => Site::count(),
            'paid'      => Site::where('status', 'paid')->count(),
            'published' => Site::where('status', 'published')->count(),
            'clients'   => Site::whereNotNull('owner_email')->distinct('owner_email')->count('owner_email'),
        ];

        $sites = Site::query()
            ->orderByDesc('created_at')
            ->take(60)
            ->get(['id', 'slug', 'name', 'city', 'sector', 'status', 'custom_domain', 'subdomain', 'hosting_plan', 'owner_email', 'rating', 'reviews_count', 'created_at']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'sites' => $sites,
        ]);
    }
}
