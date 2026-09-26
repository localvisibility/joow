<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\GooglePlaces;
use App\Services\SectorDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Tunnel public : générer un site depuis sa fiche Google, SANS compte.
 */
class PublicSiteController extends Controller
{
    public function landing()
    {
        // Preuve sociale réelle (issue du parc migré).
        $stats = [
            'sites'   => max(Site::count(), (int) config('joow.landing.sites_min', 0)),
            'rating'  => round((float) Site::whereNotNull('rating')->where('rating', '>', 0)->avg('rating'), 1) ?: 4.8,
            'sectors' => Site::whereNotNull('sector')->distinct('sector')->count('sector'),
        ];

        return Inertia::render('Public/Landing', [
            'stats' => $stats,
        ]);
    }

    /** Autocomplétion d'établissements (JSON) pour la recherche intelligente. */
    public function search(Request $request, GooglePlaces $places)
    {
        return response()->json([
            'results' => $places->autocomplete((string) $request->query('q', '')),
        ]);
    }

    public function generate(Request $request, GooglePlaces $places, SectorDetector $detector)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:300'],
            'email' => ['nullable', 'email'],
        ]);

        $b = $places->lookup($data['query']);
        if (! $b) {
            return back()->withErrors(['query' => "Fiche introuvable. Colle un lien Google Maps ou « nom + ville »."]);
        }

        $sector = $detector->detect($b['name'], $b['types'] ?? []);
        $slug = Str::slug($b['name']).'-'.Str::lower(Str::random(4));

        $site = Site::create([
            'slug'          => $slug,
            'name'          => $b['name'],
            'sector'        => $sector,
            'status'        => 'generating',
            'place_id'      => $b['place_id'],
            'city'          => $b['city'],
            'address'       => $b['address'],
            'phone'         => $b['phone'],
            'rating'        => $b['rating'],
            'reviews_count' => $b['reviews_count'],
            'maps_url'      => $b['maps_url'],
            'lat'           => $b['lat'],
            'lng'           => $b['lng'],
            'owner_email'   => $data['email'] ?? null,
            'source'        => 'joow-public',
            'preview_url'   => 'https://'.$slug.'.joow.fr',
        ]);

        $job = GenerationJob::create([
            'status'    => 'pending',
            'sector'    => $sector,
            'site_slug' => $slug,
            'input'     => ['place_id' => $b['place_id'], 'email' => $data['email'] ?? null],
        ]);

        GenerateSiteJob::dispatch($site, $job->id);

        // Le visiteur (même sans compte) peut retoucher son site dans le Studio avant de le mettre en ligne.
        $request->session()->push('joow_sites', $slug);

        return redirect()->route('public.site', $slug);
    }

    public function show(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail();

        return Inertia::render('Public/Site', [
            'site' => $site->only(['slug', 'name', 'city', 'sector', 'status', 'rating', 'reviews_count', 'preview_url']),
            // Accès au Studio : créateur de la session, propriétaire connecté ou admin
            'can_edit' => $site->editableBy($request->user(), $request->session()->get('joow_sites', [])),
        ]);
    }

    public function status(string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail(['status', 'slug']);

        return response()->json([
            'status'   => $site->status,
            'ready'    => in_array($site->status, ['preview', 'paid', 'published'], true),
            'failed'   => $site->status === 'failed',
            'live_url' => 'https://'.$site->slug.'.joow.fr',
        ]);
    }

    /** Relance la génération d'un site en échec. */
    public function retry(string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail();

        if (! in_array($site->status, ['failed', 'preview'], true)) {
            return redirect()->route('public.site', $slug);
        }

        $site->update(['status' => 'generating']);

        $job = GenerationJob::create([
            'status'    => 'pending',
            'sector'    => $site->sector,
            'site_slug' => $site->slug,
            'input'     => ['place_id' => $site->place_id, 'retry' => true],
        ]);

        GenerateSiteJob::dispatch($site, $job->id);

        return redirect()->route('public.site', $slug);
    }
}
