<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\GooglePlaces;
use App\Services\Notifier;
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

        $sector = $detector->detect($b['name'], $b['types'] ?? [], ['primary_type' => $b['primary_type'] ?? null, 'address' => $b['address'] ?? null, 'reviews' => $b['reviews'] ?? []]);
        $slug = Site::makeSlug($b['name']);

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
            'site_data'     => ['attribution' => $request->session()->get('attribution'), 'tracking' => \App\Services\MetaConversions::context($request)],
        ]);
        app(\App\Services\MetaConversions::class)->event('StartGeneration', ['source' => 'google', 'sector' => $sector], $data['email'] ?? null, null, null, $request);

        $job = GenerationJob::create([
            'status'    => 'pending',
            'sector'    => $sector,
            'site_slug' => $slug,
            'input'     => ['place_id' => $b['place_id'], 'email' => $data['email'] ?? null],
        ]);

        GenerateSiteJob::dispatch($site, $job->id);

        // Le visiteur (même sans compte) peut retoucher son site dans le Studio avant de le mettre en ligne.
        $request->session()->push('joow_sites', $slug);
        if (! empty($data['email'])) {
            [$u, $c] = app(\App\Services\Accounts::class)->ensure($data['email'], $request->session()->get('joow_sites', []), 'site');
            app(\App\Services\Accounts::class)->signInIfNew($u, $c);
        }

        return redirect()->route('public.site', $slug);
    }

    public function show(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->first();
        if (! $site && ($moved = Site::byOldSlug($slug))) {
            $guest = (array) $request->session()->get('joow_sites', []);
            if (in_array($slug, $guest, true)) {
                $request->session()->put('joow_sites', array_values(array_unique([...$guest, $moved->slug])));
            }

            return redirect()->route('public.site', $moved->slug, 301);
        }
        abort_unless($site, 404);
        $site->track('preview');

        $ttl = (int) config('services.preview_ttl_days', 7);

        return Inertia::render('Public/Site', [
            'site' => $site->only(['slug', 'name', 'city', 'sector', 'status', 'rating', 'reviews_count', 'preview_url', 'source']),
            // Email saisi à la génération : pré-rempli dans le tunnel de paiement
            'email' => $site->owner_email ?: ($request->user()?->email),
            // Accès au Studio : créateur de la session, propriétaire connecté ou admin
            'can_edit' => $site->editableBy($request->user(), $request->session()->get('joow_sites', [])),
            // Le Studio est réservé aux sites rattachés à un email (ou à un compte)
            'has_email' => (bool) ($site->owner_email || $site->user_id),
            // Sans email, l'aperçu expire : nombre de jours restants
            'expires_in_days' => max(0, $ttl - (int) $site->created_at->diffInDays(now())),
            // Refonte : adresse de l'ancien site pour le comparatif avant / après
            'old_url' => $site->site_data['import']['url'] ?? null,
            'combo' => (bool) ($site->place_id && ! empty($site->site_data['import']['pages'])),
        ]);
    }

    public function status(string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail(['status', 'slug', 'place_id', 'site_data']);
        $progress = GenerationJob::where('site_slug', $slug)->latest('id')->value('result') ?? [];

        return response()->json([
            'status'   => $site->status,
            // Découverts pendant la génération : site lu, combinaison fiche + site
            'old_url'  => $site->site_data['import']['url'] ?? null,
            'combo'    => (bool) ($site->place_id && ! empty($site->site_data['import']['pages'])),
            'step'     => $progress['step'] ?? null,
            'progress' => \Illuminate\Support\Arr::only($progress, ['step', 'source', 'name', 'city', 'rating', 'reviews_count', 'sector', 'color', 'font', 'photos', 'photo_notes', 'hero', 'hero_title', 'tagline', 'services', 'pages', 'timing', 'website', 'site_pages', 'site_images', 'stock_slots', 'stock_filled']),
            'ready'    => in_array($site->status, ['preview', 'paid', 'published'], true),
            'failed'   => $site->status === 'failed',
            'live_url' => 'https://'.$site->slug.'.joow.fr',
        ]);
    }

    /**
     * Rattache un email au site (pendant ou après la génération) : il sert à retrouver le site
     * et conditionne l'accès au Studio. Le lien privé part tout de suite si le site est prêt,
     * sinon à la fin de la génération.
     */
    public function email(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $guest = $request->session()->get('joow_sites', []);

        abort_unless($site->editableBy($request->user(), $guest), 403);

        if ($site->user_id && $site->user && strtolower($site->user->email) !== strtolower($data['email'])) {
            return response()->json(['message' => 'Ce site est déjà rattaché à un compte.'], 422);
        }
        if (! $site->user_id) {
            $site->update(['owner_email' => strtolower($data['email'])]);
        }
        // Compte créé (sans mot de passe) et sites de la session rattachés
        [$user, $created] = app(\App\Services\Accounts::class)->ensure($data['email'], array_unique(array_merge($guest, [$slug])), 'site');
        app(\App\Services\Accounts::class)->signInIfNew($user, $created);

        $ready = in_array($site->status, ['preview', 'paid', 'published'], true);
        if ($ready) {
            app(\App\Services\Accounts::class)->welcome($user, $site->fresh());
        }

        return response()->json(['ok' => true, 'sent' => $ready, 'studio' => route('sites.editor', $slug), 'track' => \App\Services\Tracking::pull()]);
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
