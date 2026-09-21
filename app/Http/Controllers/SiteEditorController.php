<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\GeminiEditor;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Éditeur de site "Studio" : édition en place (aperçu same-origin),
 * panneau de contenu, images, formulaire, style, et assistant IA.
 */
class SiteEditorController extends Controller
{
    /** Chemins modifiables (préfixes) dans site_data. */
    private const EDITABLE_PREFIXES = ['content.', 'images.', 'booking.', 'business.name', 'business.phone', 'business.address', 'business.email', 'business.opening_hours'];

    public function show(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);

        return Inertia::render('SiteEditor', [
            'site' => [
                'slug'      => $site->slug,
                'name'      => $site->name,
                'sector'    => $site->sector,
                'status'    => $site->status,
                'live_url'  => 'https://'.$site->slug.'.joow.fr',
                'editable'  => ! empty($site->site_data['content']),
                'has_place' => ! empty($site->place_id),
            ],
        ]);
    }

    /** Aperçu same-origin rendu depuis la base, avec le runtime d'édition. */
    public function preview(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);

        return response($renderer->html($site, editMode: true), 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /** État complet pour le panneau. */
    public function state(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);

        return response()->json($this->stateOf($site));
    }

    /** Modifie un ou plusieurs champs (dot-path => valeur) puis publie. */
    public function bulk(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['patch' => ['required', 'array', 'max:60']]);

        $siteData = $site->site_data ?? [];
        foreach ($data['patch'] as $path => $value) {
            abort_unless($this->isEditable((string) $path), 422, "Champ non modifiable : $path");
            Arr::set($siteData, (string) $path, $this->clean($value));
        }
        $site->update(['site_data' => $siteData]);

        $pub = ! empty($siteData['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Alias mono-champ. */
    public function content(Request $request, string $slug, SiteRenderer $renderer)
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:120']]);
        $request->merge(['patch' => [$data['path'] => $request->input('value')]]);

        return $this->bulk($request, $slug, $renderer);
    }

    /** Upload d'image (hero, à-propos, galerie…). */
    public function image(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']]);

        $file = $request->file('file');
        $name = Str::lower(Str::random(12)).'.'.($file->extension() ?: 'jpg');
        Storage::disk('local')->putFileAs("uploads/{$site->slug}", $file, $name);

        return response()->json(['url' => route('media.show', [$site->slug, $name])]);
    }

    /** Visibilité / ordre des sections. */
    public function sections(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'order'  => ['nullable', 'array', 'max:12'],
            'order.*' => ['string', 'max:30'],
            'hidden' => ['nullable', 'array'],
        ]);

        $siteData = $site->site_data ?? [];
        Arr::set($siteData, 'content.sections', [
            'order'  => array_values($data['order'] ?? []),
            'hidden' => array_map(fn ($v) => (bool) $v, $data['hidden'] ?? []),
        ]);
        $site->update(['site_data' => $siteData]);
        $pub = $renderer->publish($site);

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Couleur d'accent et police. */
    public function style(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'accent' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'font'   => ['nullable', 'string', 'max:40'],
        ]);

        $siteData = $site->site_data ?? [];
        if (! empty($data['accent'])) {
            $siteData['accent'] = '#'.ltrim($data['accent'], '#');
        }
        if (array_key_exists('font', $data)) {
            $siteData['font'] = $data['font'] ?: null;
        }
        $site->update(['site_data' => $siteData]);
        $pub = ! empty($siteData['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['ok' => true, 'accent' => $siteData['accent'] ?? null, 'font' => $siteData['font'] ?? null, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Compat : ancien endpoint couleur. */
    public function accent(Request $request, string $slug, SiteRenderer $renderer)
    {
        return $this->style($request, $slug, $renderer);
    }

    /** Force la (re)publication du site en ligne. */
    public function publish(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $pub = $renderer->publish($site);

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Assistant IA : applique une instruction, republie. */
    public function chat(Request $request, string $slug, GeminiEditor $editor, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:600']]);

        // L'appel Gemini peut prendre 20-60 s : ne pas se faire tuer par max_execution_time.
        @set_time_limit(150);

        $content = $site->site_data['content'] ?? [];
        if (! $content) {
            return response()->json(['reply' => 'Ce site doit d\'abord être régénéré pour activer l\'édition IA.', 'version' => null], 422);
        }

        $out = $editor->edit($content, $data['message'], ['name' => $site->name, 'sector' => $site->sector]);

        $siteData = $site->site_data ?? [];
        // On préserve les réglages non gérés par l'IA (sections, overrides).
        $siteData['content'] = array_merge($content, $out['content']);
        if ($out['accent']) {
            $siteData['accent'] = $out['accent'];
        }
        $site->update(['site_data' => $siteData]);
        $pub = $renderer->publish($site);

        return response()->json([
            'reply'   => $out['reply'],
            'accent'  => $siteData['accent'] ?? null,
            'version' => now()->timestamp,
            'queued'  => $pub['queued'],
            'state'   => $this->stateOf($site->fresh()),
        ]);
    }

    /** Active/désactive un module (ex : réservation). */
    public function module(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'enabled' => ['required', 'boolean']]);

        $modules = $site->modules ?: ['booking' => true];
        $modules[$data['key']] = $data['enabled'];
        $site->update(['modules' => $modules]);
        $pub = ! empty($site->site_data['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['modules' => $modules, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
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

    // ───────────────────────────── helpers ─────────────────────────────

    private function stateOf(Site $site): array
    {
        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $sector = $site->sector ?: 'service';
        $cfg = config("sectors.$sector") ?? config('sectors.service');

        return [
            'content'  => $d['content'] ?? [],
            'business' => Arr::only($b, ['name', 'phone', 'address', 'city', 'email', 'opening_hours', 'rating', 'reviews_count']),
            'photos'   => array_values(array_filter($b['photos'] ?? [])),
            'images'   => $d['images'] ?? [],
            'booking'  => $d['booking'] ?? [],
            'accent'   => $d['accent'] ?? $cfg['color'],
            'font'     => $d['font'] ?? null,
            'modules'  => $site->modules ?: ['booking' => true],
            'defaults' => ['cta' => $cfg['cta'], 'label' => $cfg['label'], 'color' => $cfg['color']],
            'live_url' => 'https://'.$site->slug.'.joow.fr',
            'version'  => optional($site->updated_at)->timestamp ?? now()->timestamp,
        ];
    }

    private function isEditable(string $path): bool
    {
        if (! preg_match('/^[a-z0-9_.]+$/i', $path)) {
            return false;
        }
        foreach (self::EDITABLE_PREFIXES as $p) {
            if ($path === rtrim($p, '.') || Str::startsWith($path, $p)) {
                return true;
            }
        }

        return false;
    }

    private function clean(mixed $v): mixed
    {
        if (is_string($v)) {
            return Str::limit(trim(strip_tags($v)), 5000, '');
        }
        if (is_array($v)) {
            return array_map(fn ($x) => $this->clean($x), $v);
        }

        return is_bool($v) || is_numeric($v) || $v === null ? $v : (string) $v;
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
