<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Boîte de réception des demandes, côté client connecté (espace).
 */
class LeadsInboxController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        // Slugs des sites du client (ou tous pour un opérateur).
        $slugs = $isAdmin
            ? null
            : Site::where('user_id', $user->id)->orWhere('owner_email', $user->email)->pluck('slug');

        $query = Lead::query()->orderByDesc('created_at');
        if (! $isAdmin) {
            $query->whereIn('site_slug', $slugs ?? []);
        }

        $leads = $query->take(200)->get();

        // Nom du site pour l'affichage
        $names = Site::whereIn('slug', $leads->pluck('site_slug')->unique())->pluck('name', 'slug');
        $leads->each(fn ($l) => $l->site_name = $names[$l->site_slug] ?? $l->site_slug);

        return Inertia::render('Leads', [
            'leads'   => $leads,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $lead->update($request->validate([
            'status' => ['required', 'in:new,read,archived'],
        ]));

        return back();
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        $owns = Site::where('slug', $lead->site_slug)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))
            ->exists();
        abort_unless($owns, 403);
    }
}
