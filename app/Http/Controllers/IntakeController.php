<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\Gemini;
use App\Services\ImageStore;
use App\Services\SectorDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Création d'un site sans fiche Google : un mini-questionnaire (nom et ville, métier, coordonnées,
 * prestations, photos) alimente la même génération que le parcours Google.
 */
class IntakeController extends Controller
{
    public function create(Request $request)
    {
        $sectors = collect(config('sectors'))
            ->map(fn ($c, $k) => ['key' => $k, 'label' => $c['label'], 'icon' => $c['icon'] ?? 'fa-store', 'keywords' => array_slice(array_values($c['keywords'] ?? []), 0, 12)])
            ->values()->all();

        return Inertia::render('Public/Intake', [
            'sectors' => $sectors,
            'prefill' => ['query' => (string) $request->query('q', ''), 'email' => (string) $request->query('email', ''), 'from_url' => (string) $request->query('from_url', '')],
            'notice' => (string) $request->session()->get('notice', ''),
            'maxPhotos' => 8,
        ]);
    }

    /** Suggestions de prestations et points forts pour un métier (IA, mises en cache 24 h). */
    public function suggestions(Request $request)
    {
        $sector = (string) $request->query('sector', 'service');
        $hint = Str::limit(trim((string) $request->query('hint', '')), 80, '');
        $cfg = config("sectors.$sector");
        $label = $cfg['label'] ?? ($hint ?: 'Services');
        $key = 'joow:intake:sugg:'.md5($sector.'|'.($cfg ? '' : $hint));

        $data = Cache::remember($key, now()->addDay(), function () use ($label, $hint, $cfg) {
            $fallback = ['services' => ['Devis gratuit', 'Intervention rapide', 'Conseil personnalisé', 'Suivi après prestation'], 'strengths' => ['Devis gratuit', 'Réponse sous 24 h', 'Travail soigné', 'Prix transparents', 'Entreprise locale', 'Garantie']];
            if (! Gemini::enabled()) {
                return $fallback;
            }
            $metier = $cfg ? $label : ($hint ?: $label);
            $prompt = "Métier : {$metier} (France). Réponds UNIQUEMENT en JSON : {\"services\": [8 prestations courantes de ce métier, 2 à 4 mots chacune, les plus demandées d'abord], \"strengths\": [8 points forts crédibles qu'un client attend de ce métier, 2 à 3 mots chacun, ex : « Devis gratuit », « Urgences 7j/7 »]}. Pas de doublon, pas de superlatif vide.";
            try {
                $res = Gemini::generate([
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.4, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 600],
                ], 20, 'IntakeSuggestions', true);
                $d = json_decode(Gemini::text($res), true);
                $clean = fn ($v) => collect((array) $v)->map(fn ($s) => Str::limit(trim((string) $s), 40, ''))->filter()->unique()->take(8)->values()->all();
                $out = ['services' => $clean($d['services'] ?? []), 'strengths' => $clean($d['strengths'] ?? [])];

                return $out['services'] ? $out : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        });

        return response()->json($data);
    }

    /** Téléversement des photos (jusqu'à 8), stockées dans un dossier temporaire lié à la session. */
    public function photos(Request $request, ImageStore $images)
    {
        $request->validate([
            'photos'   => ['required', 'array', 'max:8'],
            'photos.*' => ['image', 'max:12288'],
        ]);
        $token = $this->token($request);

        $out = [];
        foreach ($request->file('photos', []) as $file) {
            try {
                $name = $images->store($file, "intake-$token");
                $out[] = ['id' => $name, 'url' => route('media.show', ['slug' => "intake-$token", 'file' => $name])];
            } catch (\Throwable) {
                // un fichier illisible n'empêche pas les autres
            }
        }

        return response()->json(['photos' => $out]);
    }

    public function store(Request $request, SectorDetector $detector, ImageStore $images)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:120'],
            'city'        => ['required', 'string', 'min:2', 'max:120'],
            'address'     => ['nullable', 'string', 'max:200'],
            'zone'        => ['nullable', 'string', 'max:200'],
            'sector'      => ['nullable', 'string', 'max:60'],
            'sector_hint' => ['nullable', 'string', 'max:120'],
            'phone'       => ['required', 'string', 'min:6', 'max:30'],
            'email'       => ['required', 'email:rfc', 'max:190'],
            'services'    => ['nullable', 'array', 'max:12'],
            'services.*'  => ['string', 'max:60'],
            'strengths'   => ['nullable', 'array', 'max:10'],
            'strengths.*' => ['string', 'max:40'],
            'description' => ['nullable', 'string', 'max:800'],
            'since'       => ['nullable', 'integer', 'min:1800', 'max:'.date('Y')],
            'hours'       => ['nullable', 'string', 'max:300'],
            'photos'      => ['nullable', 'array', 'max:8'],
            'photos.*'    => ['string', 'max:80'],
        ]);

        $sectors = config('sectors');
        $sector = ($data['sector'] ?? null) && isset($sectors[$data['sector']]) && $data['sector'] !== 'service'
            ? $data['sector']
            : $detector->detect($data['name'], [], ['primary_type' => $data['sector_hint'] ?? null, 'address' => $data['city'], 'description' => $data['description'] ?? null]);

        if ($msg = app(\App\Services\CreationGuard::class)->check($request, $data['email'])) {
            return back()->withErrors(['name' => $msg]);
        }
        $slug = Site::makeSlug($data['name']);
        $photos = $images->move($data['photos'] ?? [], 'intake-'.$this->token($request), $slug);

        $site = Site::create([
            'slug'        => $slug,
            'name'        => $data['name'],
            'sector'      => $sector,
            'status'      => 'generating',
            'place_id'    => null,
            'creator_ip'  => $request->ip(),
            'creator_device' => \App\Services\CreationGuard::device($request),
            'city'        => $data['city'],
            'address'     => ($data['address'] ?? null) ?: $data['city'],
            'phone'       => $data['phone'],
            'owner_email' => $data['email'],
            'source'      => 'questionnaire',
            'preview_url' => 'https://'.$slug.'.joow.fr',
            'site_data'   => ['intake' => array_merge($data, ['photos' => $photos]), 'attribution' => $request->session()->get('attribution'), 'tracking' => \App\Services\MetaConversions::context($request)],
        ]);
        app(\App\Services\MetaConversions::class)->event('StartGeneration', ['source' => 'intake', 'sector' => $sector], $data['email'], null, null, $request);

        $job = GenerationJob::create([
            'status'    => 'pending',
            'sector'    => $sector,
            'site_slug' => $slug,
            'input'     => ['intake' => true, 'email' => $data['email']],
        ]);
        GenerateSiteJob::dispatch($site, $job->id);
        $request->session()->push('joow_sites', $slug);
        [$u, $c] = app(\App\Services\Accounts::class)->ensure($data['email'], $request->session()->get('joow_sites', []), 'intake');
        app(\App\Services\Accounts::class)->signInIfNew($u, $c);

        return redirect()->route('public.site', $slug);
    }

    private function token(Request $request): string
    {
        if (! $request->session()->has('intake_token')) {
            $request->session()->put('intake_token', Str::lower(Str::random(12)));
        }

        return $request->session()->get('intake_token');
    }
}
