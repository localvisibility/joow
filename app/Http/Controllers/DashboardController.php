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
            'sites'    => (clone $scope())->count(),
            // En ligne = payé (abonnement ou Liberté) ou publié ; aperçus = générés, pas encore payés
            'online'   => (clone $scope())->whereIn('status', ['paid', 'published'])->count(),
            'previews' => (clone $scope())->where('status', 'preview')->count(),
            'clients'  => $isAdmin
                ? Site::whereNotNull('owner_email')->distinct('owner_email')->count('owner_email')
                : null,
        ];

        // Recherche côté serveur : tout le parc, pas seulement les sites affichés
        $q = trim((string) $request->query('q', ''));
        $limit = $isAdmin ? 120 : 60;
        $query = $scope();
        if ($q !== '') {
            $like = '%'.mb_strtolower($q).'%';
            $query->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$like])->orWhereRaw('LOWER(COALESCE(custom_domain, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(owner_email, \'\')) LIKE ?', [$like]));
        }
        $total = (clone $query)->count();
        // Sites en ligne d'abord (les clients), puis les plus récents
        $sites = $query->orderByRaw("CASE WHEN status IN ('paid', 'published') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->take($limit)
            ->get(['id', 'slug', 'name', 'city', 'sector', 'status', 'custom_domain', 'subdomain', 'hosting_plan', 'owner_email', 'rating', 'reviews_count', 'created_at']);

        return Inertia::render('Dashboard', [
            'stats'   => $stats,
            'sites'   => $sites,
            'q'       => $q,
            'total'   => $total,
            'isAdmin' => $isAdmin,
        ]);
    }
}
