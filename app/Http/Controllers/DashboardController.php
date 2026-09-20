<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        // Opérateur : tout le parc. Client : uniquement ses sites
        // (rattachés par user_id, ou par email pour les fiches migrées sans user_id).
        $scope = fn () => $isAdmin
            ? Site::query()
            : Site::query()->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhere('owner_email', $user->email));

        $stats = [
            'sites'     => (clone $scope())->count(),
            'paid'      => (clone $scope())->where('status', 'paid')->count(),
            'published' => (clone $scope())->where('status', 'published')->count(),
            'clients'   => $isAdmin
                ? Site::whereNotNull('owner_email')->distinct('owner_email')->count('owner_email')
                : null,
        ];

        $sites = $scope()
            ->orderByDesc('created_at')
            ->take($isAdmin ? 120 : 60)
            ->get(['id', 'slug', 'name', 'city', 'sector', 'status', 'custom_domain', 'subdomain', 'hosting_plan', 'owner_email', 'rating', 'reviews_count', 'created_at']);

        return Inertia::render('Dashboard', [
            'stats'   => $stats,
            'sites'   => $sites,
            'isAdmin' => $isAdmin,
        ]);
    }
}
