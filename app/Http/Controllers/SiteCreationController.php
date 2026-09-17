<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\GooglePlaces;
use App\Services\SectorDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SiteCreationController extends Controller
{
    public function create()
    {
        return Inertia::render('Sites/Create');
    }

    /** Recherche la fiche Google et renvoie un aperçu (nom, secteur détecté, avis…). */
    public function lookup(Request $request, GooglePlaces $places, SectorDetector $detector)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        $business = $places->lookup($data['query']);

        if (! $business) {
            return back()->withErrors(['query' => "Aucune fiche trouvée. Essayez un lien Google Maps ou « nom + ville »."]);
        }

        $business['sector'] = $detector->detect($business['name'], $business['types'] ?? []);

        return Inertia::render('Sites/Create', ['business' => $business]);
    }

    /** Crée l'enregistrement du site depuis la fiche validée (génération/déploiement : étape suivante). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'place_id'      => ['required', 'string'],
            'name'          => ['required', 'string'],
            'sector'        => ['required', 'string'],
            'city'          => ['nullable', 'string'],
            'address'       => ['nullable', 'string'],
            'phone'         => ['nullable', 'string'],
            'email'         => ['nullable', 'email'],
            'rating'        => ['nullable', 'numeric'],
            'reviews_count' => ['nullable', 'integer'],
            'maps_url'      => ['nullable', 'string'],
            'lat'           => ['nullable', 'numeric'],
            'lng'           => ['nullable', 'numeric'],
        ]);

        $slug = Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        $site = Site::create([
            ...$data,
            'slug'        => $slug,
            'status'      => 'preview',
            'source'      => 'joow',
            'owner_email' => $request->user()->email,
            'user_id'     => $request->user()->id,
            'preview_url' => 'https://'.$slug.'.joow.fr',
        ]);

        // TODO incrément suivant : dispatch GenerateSiteJob($site) -> contenu IA + template + déploiement.

        return redirect()->route('dashboard')->with('flash', "Site « {$site->name} » créé. Génération à venir.");
    }
}
