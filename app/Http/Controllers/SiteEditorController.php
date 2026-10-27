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

        // Sans email ni compte, pas de Studio : retour à l'aperçu, qui demande l'email
        if (! $site->user_id && ! $site->owner_email && ! $request->user()?->isAdmin()) {
            return redirect(route('public.site', $slug).'?studio=1');
        }

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
                'pro'     => ['price' => 39, 'trial' => (int) config('services.stripe.trial_days', 0), 'ready' => (bool) config('services.stripe.price_pro')],
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

    /** En-tête : logo (import, retrait, logo IA choisi), fond, taille, inversion, nom affiché. */
    public function brand(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'file'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'logo_url'  => ['nullable', 'string', 'max:300'],
            'remove'    => ['nullable', 'boolean'],
            'bg'        => ['nullable', 'in:auto,none,light,dark,accent'],
            'size'      => ['nullable', 'in:sm,md,lg'],
            'invert'    => ['nullable', 'boolean'],
            'show_name' => ['nullable', 'boolean'],
        ]);
        $d = $site->site_data ?? [];
        $brand = $d['brand'] ?? [];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->extension() ?: 'png');
            if ($ext === 'svg') {
                $svg = app(\App\Services\LogoStudio::class)->sanitize((string) file_get_contents($file->getRealPath()), '') ?: null;
                $svg = $svg ?: preg_replace(['#<script\b[^>]*>.*?</script>#is', '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i'], '', (string) file_get_contents($file->getRealPath()));
                $name = app(\App\Services\ImageStore::class)->storeSvg($svg, $site->slug, 'logo');
                abort_unless($name, 422, 'SVG illisible.');
            } else {
                $name = 'logo-'.Str::lower(Str::random(8)).'.'.$ext;
                Storage::disk('local')->putFileAs("uploads/{$site->slug}", $file, $name);
            }
            $brand['logo'] = route('media.show', [$site->slug, $name]);
            $brand['hide'] = false;
            unset($brand['tone']);
        }
        if (! empty($data['logo_url'])) {
            $prefix = rtrim(route('media.show', [$site->slug, 'x']), 'x');
            abort_unless(Str::startsWith($data['logo_url'], [$prefix, '/media/'.$site->slug.'/']), 422, 'Logo non reconnu.');
            $brand['logo'] = $data['logo_url'];
            $brand['hide'] = false;
            unset($brand['tone']);
        }
        if (! empty($data['remove'])) {
            $brand['hide'] = true;
        }
        foreach (['bg', 'size'] as $k) {
            if (! empty($data[$k])) {
                $brand[$k] = $data[$k];
            }
        }
        foreach (['invert', 'show_name'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null) {
                $brand[$k] = (bool) $data[$k];
            }
        }
        $d['brand'] = $brand;
        $site->update(['site_data' => $d]);
        $pub = ! empty($d['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['ok' => true, 'brand' => \App\Services\LogoStudio::settings($site->fresh()->site_data ?? []), 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Logo IA : 4 propositions vectorielles (2 crédits), à choisir dans le Studio. */
    public function logoAi(Request $request, string $slug, \App\Services\LogoStudio $logos)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['style' => ['nullable', 'string', 'max:30'], 'hint' => ['nullable', 'string', 'max:300']]);
        @set_time_limit(150);
        $credits = app(\App\Services\AiCredits::class);
        $credits->refresh($site);
        if ($credits->balance($site) < 2) {
            return response()->json(['error' => 'no_credits', 'message' => 'Il faut 2 crédits IA pour générer des logos.', 'credits' => $credits->state($site)], 402);
        }
        $out = $logos->generate($site, $data['style'] ?? 'épuré', $data['hint'] ?? '');
        if (! $out) {
            return response()->json(['message' => 'La génération n\'a rien donné cette fois-ci, réessayez dans un instant ou changez de style.'], 503);
        }
        $credits->charge($site, 2, ['logo_ai' => count($out), 'style' => $data['style'] ?? 'épuré']);

        return response()->json(['logos' => $out, 'credits' => $credits->state($site)]);
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

        // Crédits IA : renouvellement éventuel du quota, puis solde requis
        $credits = app(\App\Services\AiCredits::class);
        $credits->refresh($site);
        if ($credits->balance($site) <= 0) {
            return response()->json(['error' => 'no_credits', 'reply' => 'Vous n\'avez plus de crédits IA. Les modifications manuelles restent illimitées.', 'credits' => $credits->state($site)], 402);
        }

        $history = array_slice($site->site_data['chat'] ?? [], -10);
        $before = ['site_data' => $site->site_data, 'modules' => $site->modules];

        $out = $agent->run($site, $data['message'], $history);

        // Historique de conversation (persistant, borné) et instantané pour "Annuler"
        $undoId = null;
        $cost = $credits->costFor($out['ops'], $out['applied']);
        if (count($out['applied'])) {
            $undoId = Str::lower(Str::random(8));
            Cache::put("joow:snap:{$site->id}:$undoId", $before + ['cost' => $cost], now()->addDay());
        }
        $chat = array_slice(array_merge($history, [
            ['role' => 'user', 'text' => Str::limit($data['message'], 600, '…')],
            ['role' => 'ai', 'text' => $out['reply'], 'applied' => $out['applied']],
        ]), -24);
        $siteData = $out['site_data'];
        $siteData['chat'] = $chat;

        $site->update(['site_data' => $siteData, 'modules' => $out['modules'] ?: $site->modules]);
        $credits->charge($site, $cost, ['undo_id' => $undoId, 'message' => Str::limit($data['message'], 120)]);
        $pub = $renderer->publish($site);

        return response()->json([
            'reply'   => $out['reply'],
            'applied' => $out['applied'],
            'logos'   => $out['logos'] ?? [],
            'cost'    => $cost,
            'undo_id' => $undoId,
            'version' => now()->timestamp,
            'queued'  => $pub['queued'],
            'state'   => $this->stateOf($site->fresh()),
        ]);
    }

    /** Annule un tour de l'agent IA (restaure l'instantané). */
    /** Souscrire sans attendre la fin de l'essai : 1er mois prélevé maintenant, crédits Pro débloqués. */
    public function startSubscription(Request $request, string $slug, \App\Services\Subscription $subscription)
    {
        $site = $this->ownedSite($request, $slug);
        abort_unless(in_array($site->status, ['paid', 'published'], true) && $site->hosting_plan === 'pro' && $site->user, 422, 'Cette action concerne un site en ligne avec la formule Pro.');
        abort_unless($subscription->isTrialing($site), 422, 'Votre abonnement est déjà actif.');

        try {
            $r = $subscription->startNow($site);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Souscription anticipée ($slug) : ".$e->getMessage());

            return response()->json(['error' => 'Impossible de démarrer l\'abonnement maintenant. Réessayez ou contactez-nous.'], 502);
        }

        return response()->json(['ok' => true, 'invoice_paid' => $r['paid'], 'pay_url' => $r['pay_url'], 'state' => $this->stateOf($site->fresh())]);
    }

    public function revert(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['id' => ['required', 'alpha_num', 'max:12']]);
        $snap = Cache::pull("joow:snap:{$site->id}:{$data['id']}");
        abort_unless(is_array($snap), 404, 'Cet instantané a expiré.');

        $siteData = $snap['site_data'] ?? [];
        $siteData['chat'] = array_merge($site->site_data['chat'] ?? [], [['role' => 'ai', 'text' => 'Modifications annulées, retour à l\'état précédent.', 'applied' => []]]);
        $site->update(['site_data' => $siteData, 'modules' => $snap['modules'] ?? $site->modules]);
        // Le crédit dépensé est rendu
        if (($snap['cost'] ?? 0) > 0) {
            app(\App\Services\AiCredits::class)->grantWallet($site, (int) $snap['cost'], 'revert', ['undo_id' => $data['id']]);
        }
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
        $accounts = app(\App\Services\Accounts::class);
        [$user, $created] = $accounts->ensure($data['email'], array_unique(array_merge($request->session()->get('joow_sites', []), [$site->slug])), 'studio');
        $accounts->signInIfNew($user, $created);
        $accounts->welcome($user, $site->fresh());

        return response()->json(['ok' => true, 'owner_email' => $site->fresh()->owner_email, 'edit_link' => route('sites.editor', $site->slug).'?t='.$site->editToken()]);
    }

    /** Champs de réglage modifiables depuis le Studio, par module (le reste se règle dans l'espace Modules). */
    private const MODULE_SETTINGS = [
        'bot' => ['name' => 60, 'welcome' => 200, 'tone' => 30, 'practical' => 3000, 'faq' => 4000, 'instructions' => 2000, 'lead_capture' => 'bool', 'position' => 10],
    ];

    /** Active/désactive un module (ex : réservation) et enregistre ses réglages rapides. */
    public function module(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'enabled' => ['required', 'boolean'], 'config' => ['nullable', 'array']]);

        $modules = $site->modules ?: ['booking' => true];
        $current = $site->module($data['key']);
        $settings = [];
        foreach ((array) ($data['config'] ?? []) as $k => $v) {
            $rule = self::MODULE_SETTINGS[$data['key']][$k] ?? null;
            if ($rule === 'bool') {
                $settings[$k] = (bool) $v;
            } elseif (is_int($rule)) {
                $settings[$k] = mb_substr(strip_tags((string) $v), 0, $rule);
            }
        }
        // Booléen simple tant qu'aucun réglage n'est stocké ; objet { enabled, … } sinon
        $hasSettings = $settings || count(array_diff_key($current, ['enabled' => 1])) > 0;
        $modules[$data['key']] = $hasSettings ? array_merge($current, $settings, ['enabled' => $data['enabled']]) : $data['enabled'];
        $site->update(['modules' => $modules]);
        $pub = ! empty($site->site_data['content']) ? $renderer->publish($site) : ['queued' => false];

        return response()->json(['modules' => $modules, 'module_config' => $this->moduleConfig($site), 'version' => now()->timestamp, 'queued' => $pub['queued']]);
    }

    /** Réglages des modules exposés au Studio, complétés par leurs défauts. */
    private function moduleConfig(Site $site): array
    {
        return ['bot' => array_replace(\App\Services\Modules\BotAnswer::defaults(), $site->module('bot'))];
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

    /**
     * Rattache une fiche Google à un site créé sans (questionnaire, refonte) : note, avis, photos,
     * horaires et coordonnées manquantes sont repris, le contenu rédigé est conservé, le site republié.
     */
    public function attachGoogle(Request $request, string $slug, \App\Services\GooglePlaces $places, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate(['place_id' => ['required', 'string', 'max:200']]);

        $g = $places->lookup($data['place_id']);
        if (! $g) {
            return response()->json(['message' => 'Fiche Google introuvable.'], 422);
        }

        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $merged = array_merge($b, [
            'place_id'      => $g['place_id'],
            'rating'        => $g['rating'],
            'reviews_count' => $g['reviews_count'],
            'reviews'       => $g['reviews'],
            'maps_url'      => $g['maps_url'],
            'lat'           => $g['lat'],
            'lng'           => $g['lng'],
            'types'         => $g['types'],
            'primary_type'  => $b['primary_type'] ?? $g['primary_type'],
            'photos'        => array_values(array_unique(array_merge((array) ($b['photos'] ?? []), $g['photos']))),
            'opening_hours' => ! empty($b['opening_hours']) ? $b['opening_hours'] : $g['opening_hours'],
            'phone'         => $b['phone'] ?? $g['phone'],
            'address'       => ! empty($b['address']) && $b['address'] !== ($b['city'] ?? null) ? $b['address'] : $g['address'],
            'city'          => $b['city'] ?? $g['city'],
            'website'       => $b['website'] ?? $g['website'],
        ]);
        // Le module « avis » s'active si des avis arrivent
        $modules = $site->modules ?: [];
        if ($g['reviews'] && array_key_exists('reviews', $modules) && ! $modules['reviews']) {
            $modules['reviews'] = true;
        }
        $site->update([
            'place_id'      => $g['place_id'],
            'rating'        => $g['rating'],
            'reviews_count' => $g['reviews_count'],
            'maps_url'      => $g['maps_url'],
            'lat'           => $g['lat'],
            'lng'           => $g['lng'],
            'address'       => $merged['address'],
            'city'          => $merged['city'],
            'phone'         => $merged['phone'],
            'modules'       => $modules ?: null,
            'site_data'     => array_merge($d, ['business' => $merged]),
        ]);
        $renderer->publish($site->fresh());

        return response()->json(['ok' => true, 'version' => now()->timestamp, 'state' => $this->stateOf($site->fresh()), 'google' => ['name' => $g['name'], 'rating' => $g['rating'], 'reviews_count' => $g['reviews_count'], 'photos' => count($g['photos'])]]);
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
            'brand'    => \App\Services\LogoStudio::settings($d) + ['source_logo' => $b['logo'] ?? null],
            'modules'  => $site->modules ?: ['booking' => true],
            'module_config' => $this->moduleConfig($site),
            'module_catalog' => collect(config('modules'))->map(fn ($m, $k) => ['key' => $k, 'label' => $m['name'] ?? $k])->values()->all(),
            'defaults' => ['cta' => $cfg['cta'], 'label' => $cfg['label'], 'color' => $cfg['color'], 'font' => $cfg['font'] ?? 'Space Grotesk', 'theme' => $cfg['theme'] ?? 'light', 'hero' => $cfg['hero'] ?? 'editorial'],
            'live_url' => 'https://'.$site->slug.'.joow.fr',
            'paid'     => in_array($site->status, ['paid', 'published'], true),
            'owner_email' => $site->owner_email,
            'credits'  => tap(app(\App\Services\AiCredits::class), fn ($c) => $c->refresh($site))->state($site),
            'subscription' => app(\App\Services\Subscription::class)->state($site),
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
