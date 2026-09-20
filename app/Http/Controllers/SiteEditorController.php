<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\GeminiEditor;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Éditeur de site assisté par IA (façon "chat -> le site se met à jour").
 */
class SiteEditorController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);

        return Inertia::render('SiteEditor', [
            'site' => [
                'slug'     => $site->slug,
                'name'     => $site->name,
                'sector'   => $site->sector,
                'status'   => $site->status,
                'live_url' => 'https://'.$site->slug.'.joow.fr',
                'accent'   => $site->site_data['accent'] ?? (config("sectors.$site->sector.color") ?? '#4f46e5'),
                'modules'  => $site->modules ?: ['booking' => true],
                'editable' => ! empty($site->site_data['content']),
                'has_place'=> ! empty($site->place_id),
            ],
        ]);
    }

    /** Applique une instruction IA, re-render, renvoie le résultat (JSON). */
    public function chat(Request $request, string $slug, GeminiEditor $editor, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:600']]);

        $content = $site->site_data['content'] ?? [];
        if (! $content) {
            return response()->json(['reply' => 'Ce site doit d\'abord être régénéré pour activer l\'édition IA.', 'version' => null], 422);
        }

        $out = $editor->edit($content, $data['message'], ['name' => $site->name, 'sector' => $site->sector]);

        $siteData = $site->site_data ?? [];
        $siteData['content'] = $out['content'];
        if ($out['accent']) {
            $siteData['accent'] = $out['accent'];
        }
        $site->update(['site_data' => $siteData]);
        $renderer->render($site);

        return response()->json([
            'reply'   => $out['reply'],
            'accent'  => $siteData['accent'] ?? null,
            'version' => now()->timestamp,
        ]);
    }

    /** Active/désactive un module (ex : réservation) puis re-render. */
    public function module(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'key'     => ['required', 'string', 'max:40'],
            'enabled' => ['required', 'boolean'],
        ]);

        $modules = $site->modules ?: ['booking' => true];
        $modules[$data['key']] = $data['enabled'];
        $site->update(['modules' => $modules]);

        if (! empty($site->site_data['content'])) {
            $renderer->render($site);
        }

        return response()->json(['modules' => $modules, 'version' => now()->timestamp]);
    }

    /** Change la couleur d'accent puis re-render. */
    public function accent(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['accent' => ['required', 'regex:/^#?[0-9a-fA-F]{6}$/']]);

        $siteData = $site->site_data ?? [];
        $siteData['accent'] = '#'.ltrim($data['accent'], '#');
        $site->update(['site_data' => $siteData]);

        if (! empty($siteData['content'])) {
            $renderer->render($site);
        }

        return response()->json(['accent' => $siteData['accent'], 'version' => now()->timestamp]);
    }

    /** Régénère intégralement le site (repart de la fiche Google). */
    public function regenerate(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);
        abort_if(empty($site->place_id), 422, 'Fiche Google absente : régénération impossible.');

        $site->update(['status' => 'generating']);
        $job = GenerationJob::create([
            'status' => 'pending', 'sector' => $site->sector, 'site_slug' => $site->slug,
            'input' => ['place_id' => $site->place_id, 'regenerate' => true],
        ]);
        GenerateSiteJob::dispatch($site, $job->id);

        return back();
    }

    private function ownedSite(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        $owns = $user->isAdmin()
            || ($site->user_id && $site->user_id === $user->id)
            || ($site->owner_email && strtolower($site->owner_email) === strtolower($user->email));
        abort_unless($owns, 403);

        return $site;
    }
}
