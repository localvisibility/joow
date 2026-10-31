<?php

namespace App\Http\Controllers;

use App\Models\AdsPack;
use App\Models\LandingPage;
use App\Models\Site;
use App\Services\Ads\AbTest;
use App\Services\Ads\AdsStudio;
use App\Services\Ads\LandingPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Module Joow Ads (49 € HT/mois en plus de l'abonnement) : stratégie, pubs, pages d'offre A/B. */
class AdsController extends Controller
{
    public function index(Request $request, AbTest $ab)
    {
        $user = $request->user();
        $sites = Site::query()->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('user_id', $user->id)->orWhere('owner_email', $user->email)))
            ->orderByDesc('created_at')->take($user->isAdmin() ? 300 : 50)->get(['id', 'slug', 'name', 'city', 'sector', 'status', 'site_data', 'custom_domain', 'domain_status']);
        $site = $sites->firstWhere('slug', $request->query('site')) ?? $sites->first(fn ($s) => in_array($s->status, ['paid', 'published'], true)) ?? $sites->first();

        $pages = $site ? LandingPage::where('site_id', $site->id)->orderByDesc('created_at')->get()->each->setRelation('site', $site) : collect();

        return Inertia::render('Ads', [
            'sites' => $sites->map(fn ($s) => ['slug' => $s->slug, 'name' => $s->name, 'city' => $s->city, 'online' => in_array($s->status, ['paid', 'published'], true)]),
            'current' => $site?->slug,
            'active' => (bool) $site?->adsActive($user),
            'online' => $site && in_array($site->status, ['paid', 'published'], true),
            'price' => (int) config('modules.ads.price', 49),
            'ready' => (bool) config('services.stripe.price_ads') && (bool) config('cashier.secret'),
            'goals' => AdsStudio::GOALS,
            'packs' => $site ? AdsPack::where('site_id', $site->id)->latest()->take(10)->get()->map(fn ($p) => ['id' => $p->id, 'goal' => $p->goal, 'offer' => $p->offer, 'at' => $p->created_at->toIso8601String(), 'pack' => $p->pack])->all() : [],
            'pages' => $pages->map(fn (LandingPage $p) => $this->pageRow($p, $ab))->all(),
            'visuals' => $site ? app(\App\Services\Ads\AdVisuals::class)->gallery($site) : [],
            'usage' => $site ? app(\App\Services\Ads\AdVisuals::class)->usage($site) : null,
            'photos' => $site ? app(AdsStudio::class)->photos($site) : [],
            'brand' => $site ? ['name' => $site->name, 'city' => $site->city, 'color' => $site->site_data['accent'] ?? (config("sectors.{$site->sector}.color") ?? '#4f46e5'), 'rating' => $site->rating, 'reviews' => $site->reviews_count, 'phone' => $site->site_data['business']['phone'] ?? $site->phone] : null,
            'site_url' => $site ? ($site->custom_domain && $site->domain_status === 'active' ? 'https://'.$site->custom_domain : 'https://'.$site->slug.'.joow.fr') : null,
        ]);
    }

    private function pageRow(LandingPage $p, AbTest $ab): array
    {
        return ['id' => $p->id, 'title' => $p->title, 'path' => $p->path, 'goal' => $p->goal, 'status' => $p->status, 'winner' => $p->winner,
            'variants' => $p->variants, 'content' => $p->content ?: self::defaultContent($p->goal), 'url' => $p->url(), 'results' => $ab->results($p), 'published_at' => optional($p->published_at)->toIso8601String()];
    }

    /** Souscription au module (abonnement Stripe séparé, type « ads:<site> »). */
    public function subscribe(Request $request, string $slug)
    {
        $site = $this->site($request, $slug, requireActive: false);
        $user = $request->user();
        if ($site->adsActive($user)) {
            return redirect()->route('ads.index', ['site' => $slug]);
        }
        abort_unless(in_array($site->status, ['paid', 'published'], true), 422, 'Mettez d\'abord votre site en ligne : vos publicités ont besoin d\'une adresse.');
        $price = config('services.stripe.price_ads');
        abort_unless($price && config('cashier.secret'), 422, 'Le module Joow Ads sera disponible très bientôt.');

        $meta = ['type' => 'addon_ads', 'site_slug' => $slug, 'user_id' => $user->id];

        return $user->withValidStripeCustomer(fn () => $user->newSubscription('ads:'.$slug, $price)->withMetadata($meta)->checkout([
            'success_url' => route('ads.index', ['site' => $slug, 'ok' => 1]),
            'cancel_url' => route('ads.index', ['site' => $slug]),
            'metadata' => $meta,
        ])->redirect());
    }

    /** Génère une stratégie + pubs (et propose une page d'offre). */
    public function pack(Request $request, string $slug, AdsStudio $studio)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate(['goal' => ['required', 'in:'.implode(',', array_keys(AdsStudio::GOALS))], 'offer' => ['nullable', 'string', 'max:200'], 'budget' => ['nullable', 'integer', 'min:0', 'max:100000']]);
        @set_time_limit(150);
        $pack = $studio->pack($site, $data['goal'], $data['offer'] ?? null, $data['budget'] ?? null);
        if (! $pack) {
            return response()->json(['error' => 'La génération n\'a pas abouti, réessayez dans un instant.'], 502);
        }
        $p = AdsPack::create(['site_id' => $site->id, 'goal' => $data['goal'], 'offer' => $data['offer'] ?? null, 'pack' => $pack]);
        Log::info("Joow Ads : pack généré pour {$site->slug}");

        return response()->json(['id' => $p->id, 'goal' => $p->goal, 'offer' => $p->offer, 'at' => $p->created_at->toIso8601String(), 'pack' => $pack]);
    }

    /** Crée une page d'offre : variantes fournies (depuis un pack) ou rédigées par l'IA à partir d'une offre. */
    public function storePage(Request $request, string $slug, AdsStudio $studio)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate([
            'goal' => ['required', 'in:'.implode(',', array_keys(AdsStudio::GOALS))],
            'offer' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:60'],
            'variants' => ['nullable', 'array', 'max:3'],
            'content' => ['nullable', 'array'],
        ]);
        $variants = $data['variants'] ?? null;
        $content = $data['content'] ?? null;
        $title = $data['title'] ?? null;
        $path = $data['path'] ?? null;
        if (! $variants) {
            abort_if(empty($data['offer']), 422, 'Indiquez votre offre.');
            @set_time_limit(150);
            $gen = $studio->landingVariants($site, $data['goal'], $data['offer']);
            abort_unless($gen && ! empty($gen['variants']), 502, 'La génération n\'a pas abouti, réessayez dans un instant.');
            $variants = $gen['variants'];
            $content = $gen['content'] ?? null;
            $title ??= $gen['title'] ?? null;
            $path ??= $gen['path'] ?? null;
        }
        $lp = LandingPage::create([
            'site_id' => $site->id, 'site_slug' => $site->slug, 'goal' => $data['goal'],
            'title' => Str::limit($title ?: ($data['offer'] ?? 'Offre'), 120, ''),
            'path' => $this->uniquePath($site, $path ?: ($title ?: 'offre')),
            'variants' => $this->cleanVariants($variants), 'content' => self::cleanContent($content, $data['goal']), 'status' => 'draft',
        ]);

        return response()->json(['page' => $this->pageRow($lp->setRelation('site', $site), app(AbTest::class))]);
    }

    public function updatePage(Request $request, string $slug, LandingPage $page, LandingPublisher $pub)
    {
        $site = $this->site($request, $slug);
        abort_unless($page->site_id === $site->id, 404);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:120'], 'goal' => ['nullable', 'in:'.implode(',', array_keys(AdsStudio::GOALS))], 'variants' => ['nullable', 'array', 'min:1', 'max:3'], 'content' => ['nullable', 'array']]);
        $page->fill(array_filter(['title' => $data['title'] ?? null, 'goal' => $data['goal'] ?? null]));
        if (isset($data['content'])) {
            $page->content = self::cleanContent($data['content'], $page->goal);
        }
        if (isset($data['variants'])) {
            $page->variants = $this->cleanVariants($data['variants']);
            if ($page->winner && ! collect($page->variants)->contains('key', $page->winner)) {
                $page->winner = null;
            }
        }
        $page->save();
        $page->setRelation('site', $site);
        if ($page->status === 'live') {
            $pub->publish($page);
        }

        return response()->json(['page' => $this->pageRow($page, app(AbTest::class))]);
    }

    /** publish | pause | winner (garder une variante) | reset (relancer le test). */
    public function action(Request $request, string $slug, LandingPage $page, LandingPublisher $pub)
    {
        $site = $this->site($request, $slug);
        abort_unless($page->site_id === $site->id, 404);
        $data = $request->validate(['action' => ['required', 'in:publish,pause,winner,reset'], 'variant' => ['nullable', 'string', 'max:2']]);
        $page->setRelation('site', $site);
        match ($data['action']) {
            'publish' => $pub->publish($page),
            'pause' => (function () use ($page, $pub) { $pub->unpublish($page); $page->forceFill(['status' => 'paused'])->save(); })(),
            'winner' => (function () use ($page, $pub, $data) {
                abort_unless(collect($page->variants)->contains('key', $data['variant']), 422);
                $page->forceFill(['winner' => $data['variant']])->save();
                if ($page->status === 'live') {
                    $pub->publish($page);
                }
            })(),
            'reset' => (function () use ($page, $pub) {
                \Illuminate\Support\Facades\DB::table('landing_stats')->where('landing_id', $page->id)->delete();
                $page->forceFill(['winner' => null])->save();
                if ($page->status === 'live') {
                    $pub->publish($page);
                }
            })(),
        };

        return response()->json(['page' => $this->pageRow($page->fresh()->setRelation('site', $site), app(AbTest::class))]);
    }

    public function destroyPage(Request $request, string $slug, LandingPage $page, LandingPublisher $pub)
    {
        $site = $this->site($request, $slug);
        abort_unless($page->site_id === $site->id, 404);
        $pub->unpublish($page->setRelation('site', $site));
        \Illuminate\Support\Facades\DB::table('landing_stats')->where('landing_id', $page->id)->delete();
        $page->delete();

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request, string $slug, LandingPage $page, LandingPublisher $pub)
    {
        $site = $this->site($request, $slug, requireActive: false);
        abort_unless($page->site_id === $site->id, 404);

        return response($pub->html($page->setRelation('site', $site), preview: true));
    }

    /** Public : variante à montrer à un nouveau visiteur (répartition qui favorise la meilleure). */
    public function pick(string $slug, LandingPage $page, AbTest $ab)
    {
        abort_unless($page->site_slug === $slug, 404);

        return response()->json(['v' => $ab->pick($page)]);
    }

    /** Public : vue / conversion / appel d'une variante (depuis la page publiée). */
    public function hit(Request $request, string $slug, LandingPage $page)
    {
        $data = $request->validate(['v' => ['required', 'string', 'max:2'], 't' => ['required', 'in:view,conversion,call']]);
        if ($page->site_slug === $slug && $page->status === 'live') {
            AbTest::hit($page, $data['v'], $data['t']);
        }

        return response()->json(['ok' => true]);
    }

    /** Campagne créative : concepts par axe + carrousel, enregistrés dans le pack. */
    public function campaign(Request $request, string $slug, AdsStudio $studio)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate(['pack_id' => ['required', 'uuid']]);
        $pack = AdsPack::where('site_id', $site->id)->findOrFail($data['pack_id']);
        @set_time_limit(150);
        $camp = $studio->creatives($site, (array) $pack->pack, $pack->goal, $pack->offer);
        if (! $camp) {
            return response()->json(['error' => 'La création de la campagne n\'a pas abouti, réessayez dans un instant.'], 502);
        }
        $pack->pack = array_merge((array) $pack->pack, ['campaign' => $camp]);
        $pack->save();

        return response()->json(['campaign' => $camp]);
    }

    /** Pub finale d'un concept : lancée en arrière-plan (4:5, puis déclinaisons 1:1 et 9:16 à partir de la 4:5). */
    public function creative(Request $request, string $slug, \App\Services\Ads\AdVisuals $visuals)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate(['pack_id' => ['required', 'uuid'], 'key' => ['required', 'string', 'max:6'], 'format' => ['required', 'in:portrait,square,story'], 'redo' => ['nullable', 'boolean']]);
        $pack = AdsPack::where('site_id', $site->id)->findOrFail($data['pack_id']);
        $c = collect($pack->pack['campaign']['creatives'] ?? [])->firstWhere('key', $data['key']);
        abort_unless($c, 404);
        $job = $c['jobs'][$data['format']] ?? null;
        if (($job['status'] ?? '') === 'running' && now()->subMinutes(6)->lt($job['at'] ?? '2000-01-01')) {
            return response()->json(['queued' => true], 202); // déjà en cours : pas de double facturation
        }
        $pay = $visuals->payment($site, $request->user()->isAdmin());
        if (isset($pay['error'])) {
            return response()->json(['error' => $pay['error']], $pay['status']);
        }
        \App\Jobs\RenderAdJob::store($pack->id, $data['key'], function (array &$cr) use ($data) {
            $cr['jobs'][$data['format']] = ['status' => 'running', 'at' => now()->toIso8601String()];
        });
        \App\Jobs\RenderAdJob::dispatch($pack->id, $data['key'], $data['format'], $request->user()->isAdmin(), (bool) ($data['redo'] ?? false));

        return response()->json(['queued' => true], 202);
    }

    /** État des pubs d'une campagne (rendus terminés, en cours, erreurs) : interrogé toutes les quelques secondes. */
    public function campaignState(Request $request, string $slug, string $pack)
    {
        $site = $this->site($request, $slug);
        $p = AdsPack::where('site_id', $site->id)->findOrFail($pack);

        return response()->json([
            'creatives' => collect($p->pack['campaign']['creatives'] ?? [])->map(fn ($c) => ['key' => $c['key'], 'renders' => (object) ($c['renders'] ?? []),
                'jobs' => (object) collect($c['jobs'] ?? [])->map(fn ($j) => ($j['status'] ?? '') === 'running' && now()->subMinutes(6)->gte($j['at'] ?? '2000-01-01')
                    ? ['status' => 'error', 'error' => 'La création a été interrompue. Réessayez.'] : $j)->all()])->values(),
            'usage' => app(\App\Services\Ads\AdVisuals::class)->usage($site),
        ]);
    }

    /** Génère un visuel publicitaire IA (quota mensuel, puis crédits IA). */
    public function visual(Request $request, string $slug, \App\Services\Ads\AdVisuals $visuals)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate(['brief' => ['required', 'string', 'max:500'], 'format' => ['required', 'in:square,portrait,story'], 'ref' => ['nullable', 'url', 'max:500'],
            'pack_id' => ['nullable', 'uuid'], 'creative' => ['nullable', 'string', 'max:6']]);
        @set_time_limit(180);
        $ref = ($data['ref'] ?? null) && in_array($data['ref'], app(AdsStudio::class)->photos($site), true) ? $data['ref'] : null;
        $out = $visuals->generate($site, $data['brief'], $data['format'], $ref, $request->user()->isAdmin());
        if (isset($out['error'])) {
            return response()->json(['error' => $out['error']], $out['status']);
        }
        // Visuel d'un concept de campagne : on le rattache au concept
        if (! empty($data['pack_id']) && ! empty($data['creative']) && ($pack = AdsPack::where('site_id', $site->id)->find($data['pack_id']))) {
            $p = (array) $pack->pack;
            foreach ((array) ($p['campaign']['creatives'] ?? []) as $i => $c) {
                if (($c['key'] ?? '') === $data['creative']) {
                    $p['campaign']['creatives'][$i]['image'] = $out['url'];
                }
            }
            $pack->pack = $p;
            $pack->save();
        }

        return response()->json($out + ['usage' => $visuals->usage($site->fresh()), 'credits' => app(\App\Services\AiCredits::class)->state($site->fresh())]);
    }

    /** Contenu par défaut d'une page sans contenu IA (formulaire simple). */
    public static function defaultContent(string $goal): array
    {
        return [
            'form' => ['title' => $goal === 'calls' ? 'Être rappelé gratuitement' : 'Votre demande en 1 minute', 'subtitle' => 'Réponse rapide · sans engagement', 'steps' => [], 'ask_email' => false, 'ask_postcode' => false, 'button' => 'Envoyer', 'success' => 'Merci ! Nous vous recontactons très vite.'],
            'process' => [['title' => 'Votre demande', 'text' => 'En 1 minute, sans engagement.'], ['title' => 'On vous rappelle', 'text' => 'Rapidement, pour comprendre votre besoin.'], ['title' => 'Votre solution', 'text' => 'Une proposition claire et adaptée.']],
            'faq' => [], 'guarantees' => ['Sans engagement', 'Réponse rapide', 'Devis gratuit'], 'urgency' => '',
        ];
    }

    public static function cleanContent(?array $c, string $goal): array
    {
        $d = self::defaultContent($goal);
        if (! $c) {
            return $d;
        }
        $str = fn ($v, $n) => Str::limit(trim(strip_tags((string) $v)), $n, '');
        $f = (array) ($c['form'] ?? []);
        $steps = [];
        foreach (array_slice((array) ($f['steps'] ?? []), 0, 5) as $i => $st) {
            $type = in_array($st['type'] ?? '', ['choice', 'multi', 'text', 'number'], true) ? $st['type'] : 'choice';
            $opts = array_values(array_filter(array_map(fn ($o) => $str($o, 60), array_slice((array) ($st['options'] ?? []), 0, 8))));
            if (in_array($type, ['choice', 'multi'], true) && count($opts) < 2) {
                $type = 'text';
            }
            $q = $str($st['question'] ?? '', 120);
            if ($q !== '') {
                $steps[] = ['key' => Str::slug($st['key'] ?? $q, '_') ?: 'q'.$i, 'question' => $q, 'type' => $type, 'options' => in_array($type, ['choice', 'multi'], true) ? $opts : [], 'required' => (bool) ($st['required'] ?? true)];
            }
        }

        return [
            'form' => [
                'title' => $str($f['title'] ?? '', 80) ?: $d['form']['title'], 'subtitle' => $str($f['subtitle'] ?? '', 120) ?: $d['form']['subtitle'],
                'steps' => $steps, 'ask_email' => (bool) ($f['ask_email'] ?? false), 'ask_postcode' => (bool) ($f['ask_postcode'] ?? false),
                'button' => $str($f['button'] ?? '', 30) ?: $d['form']['button'], 'success' => $str($f['success'] ?? '', 200) ?: $d['form']['success'],
            ],
            'process' => array_values(array_filter(array_map(fn ($p) => ['title' => $str($p['title'] ?? '', 40), 'text' => $str($p['text'] ?? '', 140)], array_slice((array) ($c['process'] ?? []), 0, 4)), fn ($p) => $p['title'] !== '')) ?: $d['process'],
            'faq' => array_values(array_filter(array_map(fn ($q) => ['q' => $str($q['q'] ?? '', 140), 'a' => $str($q['a'] ?? '', 500)], array_slice((array) ($c['faq'] ?? []), 0, 6)), fn ($q) => $q['q'] !== '' && $q['a'] !== '')),
            'guarantees' => array_values(array_filter(array_map(fn ($g) => $str($g, 40), array_slice((array) ($c['guarantees'] ?? []), 0, 5)))) ?: $d['guarantees'],
            'urgency' => $str($c['urgency'] ?? '', 120),
            'theme' => in_array($c['theme'] ?? '', ['premium', 'warm', 'bold'], true) ? $c['theme'] : 'bold',
            'widget' => self::cleanWidget((array) ($c['widget'] ?? []), $str),
            // Cohérence pub → page : accroche et visuel de chaque pub (lien ?c=<clé>)
            'match' => collect((array) ($c['match'] ?? []))->take(10)->mapWithKeys(fn ($m, $k) => [Str::limit(Str::slug((string) $k), 8, '') => [
                'headline' => $str($m['headline'] ?? '', 120), 'sub' => $str($m['sub'] ?? '', 200),
                'image' => is_string($m['image'] ?? null) && str_starts_with($m['image'], 'http') ? $m['image'] : null,
            ]])->filter(fn ($m, $k) => $k !== '' && $m['headline'] !== '')->all(),
        ];
    }

    private static function cleanWidget(array $w, callable $str): array
    {
        $type = in_array($w['type'] ?? '', ['savings', 'estimate', 'beforeafter'], true) ? $w['type'] : 'none';
        $num = fn ($v, $d) => is_numeric($v) ? (float) $v : $d;
        $sv = (array) ($w['savings'] ?? []);
        $min = max(0, $num($sv['min'] ?? null, 500));
        $max = max($min + 1, $num($sv['max'] ?? null, 5000));
        $pmin = min(90, max(0, $num($sv['pct_min'] ?? null, 0)));
        $pmax = min(90, max($pmin, $num($sv['pct_max'] ?? null, 0)));
        if ($type === 'savings' && $pmax <= 0) {
            $type = 'none'; // pas de pourcentage d'économie crédible : pas de simulateur
        }
        $est = (array) ($w['estimate'] ?? []);
        $opts = [];
        foreach (array_slice((array) ($est['options'] ?? []), 0, 6) as $o) {
            $o = is_array($o) ? $o : ['label' => $o];
            $label = $str($o['label'] ?? '', 60);
            if ($label !== '') {
                $opts[] = ['label' => $label, 'from' => is_numeric($o['from'] ?? null) ? (int) $o['from'] : null, 'to' => is_numeric($o['to'] ?? null) ? (int) $o['to'] : null];
            }
        }
        // Fourchettes de prix : uniquement celles renseignées par le professionnel
        if ($type === 'estimate' && ! collect($opts)->contains(fn ($o) => $o['from'] !== null)) {
            $type = 'none';
        }
        $ba = (array) ($w['beforeafter'] ?? []);
        $isUrl = fn ($u) => is_string($u) && str_starts_with($u, 'http');
        if ($type === 'beforeafter' && ! ($isUrl($ba['before'] ?? null) && $isUrl($ba['after'] ?? null))) {
            $type = 'none';
        }

        return [
            'type' => $type, 'title' => $str($w['title'] ?? '', 60),
            'savings' => ['label' => $str($sv['label'] ?? '', 80) ?: 'Votre facture annuelle', 'unit' => $str($sv['unit'] ?? '', 12) ?: '€ / an',
                'min' => (int) $min, 'max' => (int) $max, 'default' => (int) min($max, max($min, $num($sv['default'] ?? null, ($min + $max) / 2))),
                'pct_min' => $pmin, 'pct_max' => $pmax, 'note' => $str($sv['note'] ?? '', 160) ?: 'Estimation indicative, à confirmer lors de l\'étude.'],
            'estimate' => ['question' => $str($est['question'] ?? '', 80), 'options' => $opts, 'note' => $str($est['note'] ?? '', 160) ?: 'Fourchette indicative, prix définitif après visite.'],
            'beforeafter' => ['before' => $isUrl($ba['before'] ?? null) ? $ba['before'] : null, 'after' => $isUrl($ba['after'] ?? null) ? $ba['after'] : null],
        ];
    }

    private function cleanVariants(array $variants): array
    {
        $out = [];
        foreach (array_slice(array_values($variants), 0, 3) as $i => $v) {
            $v = (array) $v;
            $out[] = [
                'key' => chr(65 + $i),
                'headline' => Str::limit(trim((string) ($v['headline'] ?? '')), 120, ''),
                'subheadline' => Str::limit(trim((string) ($v['subheadline'] ?? '')), 240, ''),
                'offer' => Str::limit(trim((string) ($v['offer'] ?? '')), 160, ''),
                'bullets' => array_values(array_filter(array_map(fn ($b) => Str::limit(trim((string) $b), 90, ''), array_slice((array) ($v['bullets'] ?? []), 0, 4)))),
                'cta' => Str::limit(trim((string) ($v['cta'] ?? 'Demander')), 30, ''),
                'image' => is_string($v['image'] ?? null) && str_starts_with($v['image'], 'https://') ? $v['image'] : null,
            ];
        }

        return $out;
    }

    private function uniquePath(Site $site, string $base): string
    {
        $base = Str::limit(Str::slug($base) ?: 'offre', 40, '');
        $path = $base;
        for ($i = 2; LandingPage::where('site_id', $site->id)->where('path', $path)->exists(); $i++) {
            $path = $base.'-'.$i;
        }

        return $path;
    }

    private function site(Request $request, string $slug, bool $requireActive = true): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user->isAdmin() || ($site->user_id && $site->user_id === $user->id) || ($site->owner_email && strtolower($site->owner_email) === strtolower($user->email)), 403);
        if ($requireActive) {
            abort_unless($site->adsActive($user), 402, 'Activez le module Joow Ads pour utiliser cette fonction.');
        }

        return $site;
    }
}
