<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\Pages\PageSchema;
use App\Services\SiteAgent;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Éditeur de site "Studio" : édition en place (aperçu same-origin), pages
 * additionnelles (blocs), style, formulaire, modules et agent IA.
 *
 * Accessible SANS compte au créateur du site (session ou lien signé) :
 * le client peaufine son site avant de payer, puis le met en ligne.
 */
class SiteEditorController extends Controller
{
    /** Chemins modifiables (préfixes) dans site_data. */
    private const EDITABLE_PREFIXES = SiteAgent::EDITABLE;

    public function show(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);

        return Inertia::render('SiteEditor', [
            'site' => [
                'slug'        => $site->slug,
                'name'        => $site->name,
                'sector'      => $site->sector,
                'status'      => $site->status,
                'live_url'    => 'https://'.$site->slug.'.joow.fr',
                'editable'    => ! empty($site->site_data['content']),
                'has_place'   => ! empty($site->place_id),
                'paid'        => in_array($site->status, ['paid', 'published'], true),
                'owner_email' => $site->owner_email,
                'edit_link'   => route('sites.editor', $site->slug).'?t='.$site->editToken(),
            ],
            'plans' => [
                'pro'     => ['price' => 39, 'trial' => (int) config('services.stripe.trial_days', 7), 'ready' => (bool) config('services.stripe.price_pro')],
                'liberte' => ['price' => 349, 'ready' => (bool) config('services.stripe.price_liberte')],
            ],
        ]);
    }

    /** Aperçu same-origin rendu depuis la base, avec le runtime d'édition. ?page=slug pour une sous-page. */
    public function preview(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $page = $request->query('page');

        return response($renderer->html($site, editMode: true, pageSlug: $page ?: null), 200, [
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
        $touchesPages = false;
        foreach ($data['patch'] as $path => $value) {
            abort_unless($this->isEditable((string) $path), 422, "Champ non modifiable : $path");
            Arr::set($siteData, (string) $path, $this->clean($value));
            $touchesPages = $touchesPages || Str::startsWith((string) $path, 'pages');
        }
        if ($touchesPages) {
            $siteData['pages'] = PageSchema::normalizePages($siteData['pages'] ?? []);
        }
        $site->update(['site_data' => $siteData]);

        $pub = ! empty($siteData['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'queued' => $pub['queued'], 'pages' => $touchesPages ? $siteData['pages'] : null]);
    }

    /** Alias mono-champ. */
    public function content(Request $request, string $slug, SiteRenderer $renderer)
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:120']]);
        $request->merge(['patch' => [$data['path'] => $request->input('value')]]);

        return $this->bulk($request, $slug, $renderer);
    }

    /** Upload d'image (hero, à-propos, galerie, blocs…). */
    public function image(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']]);

        $file = $request->file('file');
        $name = Str::lower(Str::random(12)).'.'.($file->extension() ?: 'jpg');
        Storage::disk('local')->putFileAs("uploads/{$site->slug}", $file, $name);

        return response()->json(['url' => route('media.show', [$site->slug, $name])]);
    }

    /** Visibilité / ordre des sections de l'accueil. */
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

    /** Couleur d'accent, police, thème clair/sombre, mise en page du hero. */
    public function style(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'accent'     => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'font'       => ['nullable', 'string', 'max:40'],
            'theme'      => ['nullable', 'in:light,dark'],
            'hero_style' => ['nullable', 'in:editorial,split,center'],
        ]);

        $siteData = $site->site_data ?? [];
        if (! empty($data['accent'])) {
            $siteData['accent'] = '#'.ltrim($data['accent'], '#');
        }
        if (array_key_exists('font', $data)) {
            $siteData['font'] = $data['font'] ?: null;
        }
        if (! empty($data['theme'])) {
            $siteData['theme'] = $data['theme'];
        }
        if (! empty($data['hero_style'])) {
            $siteData['hero_style'] = $data['hero_style'];
        }
        $site->update(['site_data' => $siteData]);
        $pub = ! empty($siteData['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['ok' => true, 'accent' => $siteData['accent'] ?? null, 'font' => $siteData['font'] ?? null, 'theme' => $siteData['theme'] ?? null, 'hero_style' => $siteData['hero_style'] ?? null, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Compat : ancien endpoint couleur. */
    public function accent(Request $request, string $slug, SiteRenderer $renderer)
    {
        return $this->style($request, $slug, $renderer);
    }

    /** Force la (re)publication du site (aperçu gratuit ou site payé). */
    public function publish(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $pub = $renderer->publish($site);

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Agent IA : applique une demande (textes, pages, blocs, style, modules), republie. */
    public function chat(Request $request, string $slug, SiteAgent $agent, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:1200']]);

        // L'appel Gemini peut prendre 20-90 s : ne pas se faire tuer par max_execution_time.
        @set_time_limit(170);

        if (empty($site->site_data['content'])) {
            return response()->json(['reply' => 'Ce site doit d\'abord être régénéré pour activer l\'édition IA.', 'version' => null], 422);
        }

        $history = array_slice($site->site_data['chat'] ?? [], -10);
        $before = ['site_data' => $site->site_data, 'modules' => $site->modules];

        $out = $agent->run($site, $data['message'], $history);

        // Historique de conversation (persistant, borné) et instantané pour "Annuler"
        $undoId = null;
        if (count($out['applied'])) {
            $undoId = Str::lower(Str::random(8));
            Cache::put("joow:snap:{$site->id}:$undoId", $before, now()->addDay());
        }
        $chat = array_slice(array_merge($history, [
            ['role' => 'user', 'text' => Str::limit($data['message'], 600, '…')],
            ['role' => 'ai', 'text' => $out['reply'], 'applied' => $out['applied']],
        ]), -24);
        $siteData = $out['site_data'];
        $siteData['chat'] = $chat;

        $site->update(['site_data' => $siteData, 'modules' => $out['modules'] ?: $site->modules]);
        $pub = $renderer->publish($site);

        return response()->json([
            'reply'   => $out['reply'],
            'applied' => $out['applied'],
            'undo_id' => $undoId,
            'version' => now()->timestamp,
            'queued'  => $pub['queued'],
            'state'   => $this->stateOf($site->fresh()),
        ]);
    }

    /** Annule un tour de l'agent IA (restaure l'instantané). */
    public function revert(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['id' => ['required', 'alpha_num', 'max:12']]);
        $snap = Cache::pull("joow:snap:{$site->id}:{$data['id']}");
        abort_unless(is_array($snap), 404, 'Cet instantané a expiré.');

        $siteData = $snap['site_data'] ?? [];
        $siteData['chat'] = array_merge($site->site_data['chat'] ?? [], [['role' => 'ai', 'text' => 'Modifications annulées, retour à l\'état précédent.', 'applied' => []]]);
        $site->update(['site_data' => $siteData, 'modules' => $snap['modules'] ?? $site->modules]);
        $pub = $renderer->publish($site);

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'queued' => $pub['queued'], 'state' => $this->stateOf($site->fresh())]);
    }

    /** Invité : enregistre son email pour retrouver le site (lien d'édition) et préparer la mise en ligne. */
    public function claim(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        if (! $site->user_id && (! $site->owner_email || in_array($site->slug, $request->session()->get('joow_sites', []), true))) {
            $site->update(['owner_email' => $data['email']]);
        }
        if ($user = $request->user()) {
            Site::claimFor($user, [$site->slug]);
        }
        try {
            app(\App\Services\Notifier::class)->editLink($site, $data['email']);
        } catch (\Throwable) {
            // l'envoi d'email ne doit pas bloquer l'enregistrement
        }

        return response()->json(['ok' => true, 'owner_email' => $site->fresh()->owner_email, 'edit_link' => route('sites.editor', $site->slug).'?t='.$site->editToken()]);
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
            'form'         => $d['form'] ?? (config("forms.$sector") ?? config('forms.default')),
            'form_default' => config("forms.$sector") ?? config('forms.default'),
            'pages'    => PageSchema::normalizePages($d['pages'] ?? []),
            'blocks'   => collect(PageSchema::BLOCKS)->map(fn ($v, $k) => ['type' => $k, 'label' => $v['label'], 'desc' => $v['desc']])->values()->all(),
            'chat'     => array_slice($d['chat'] ?? [], -24),
            'accent'   => $d['accent'] ?? $cfg['color'],
            'font'     => $d['font'] ?? null,
            'theme'    => $d['theme'] ?? ($cfg['theme'] ?? 'light'),
            'hero_style' => $d['hero_style'] ?? ($cfg['hero'] ?? 'editorial'),
            'modules'  => $site->modules ?: ['booking' => true],
            'module_catalog' => collect(config('modules'))->map(fn ($m, $k) => ['key' => $k, 'label' => $m['name'] ?? $k])->values()->all(),
            'defaults' => ['cta' => $cfg['cta'], 'label' => $cfg['label'], 'color' => $cfg['color'], 'font' => $cfg['font'] ?? 'Space Grotesk', 'theme' => $cfg['theme'] ?? 'light', 'hero' => $cfg['hero'] ?? 'editorial'],
            'live_url' => 'https://'.$site->slug.'.joow.fr',
            'paid'     => in_array($site->status, ['paid', 'published'], true),
            'owner_email' => $site->owner_email,
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

    /**
     * Site modifiable par le visiteur : propriétaire connecté, admin, créateur
     * de la session (sans compte) ou porteur du lien signé (?t=…).
     */
    private function ownedSite(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $guest = $request->session()->get('joow_sites', []);

        if (($t = (string) $request->query('t')) !== '' && hash_equals($site->editToken(), $t)) {
            if (! in_array($site->slug, $guest, true)) {
                $request->session()->push('joow_sites', $site->slug);
                $guest[] = $site->slug;
            }
        }

        abort_unless($site->editableBy($request->user(), $guest), 403);

        return $site;
    }
}
