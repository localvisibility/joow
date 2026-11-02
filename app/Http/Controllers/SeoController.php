<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\AiCredits;
use App\Services\Seo\SeoAi;
use App\Services\Seo\SeoAudit;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Module SEO : audit noté, conseils, réglages par page (titre, description, expression clé, images) et corrections par l'IA. */
class SeoController extends Controller
{
    public const COST = 1;

    public function index(Request $request, SeoAudit $audit)
    {
        $user = $request->user();
        $sites = Site::query()->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('user_id', $user->id)->orWhere('owner_email', $user->email)))
            ->orderByDesc('created_at')->take($user->isAdmin() ? 300 : 50)->get(['id', 'slug', 'name', 'city', 'status']);
        $site = $sites->firstWhere('slug', $request->query('site')) ?? $sites->first(fn ($s) => in_array($s->status, ['paid', 'published'], true)) ?? $sites->first();
        $site = $site ? Site::find($site->id) : null;
        $report = null;
        $error = null;
        if ($site) {
            try {
                $report = $audit->run($site);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("SEO {$site->slug} : ".$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
                $error = 'L\'analyse de ce site n\'a pas pu aboutir, réessayez dans un instant.'.($user->isAdmin() ? ' [admin] '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine() : '');
            }
        }

        return Inertia::render('Seo', [
            'error' => $error,
            'sites' => $sites->map(fn ($s) => ['slug' => $s->slug, 'name' => $s->name, 'city' => $s->city, 'online' => in_array($s->status, ['paid', 'published'], true)]),
            'current' => $site?->slug,
            'audit' => $report,
            'credits' => $site ? $this->credits($site, $user) : null,
            'suggestions' => $site ? ($site->site_data['seo']['suggestions'] ?? null) : null,
            'cost' => self::COST,
        ]);
    }

    /** Enregistre les réglages d'une page (titre, description, expression clé) et/ou des descriptions d'images. */
    public function save(Request $request, string $slug, SeoAudit $audit)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'keyword' => ['nullable', 'string', 'max:80'],
            'alts' => ['nullable', 'array', 'max:60'],
        ]);
        $this->validKey($site, $data['key']);
        $this->store($site, function (array &$seo) use ($data, $request) {
            if ($request->has('title') || $request->has('description')) {
                $seo['meta'][$data['key']] = array_filter(['title' => trim((string) ($data['title'] ?? '')), 'description' => trim((string) ($data['description'] ?? ''))]);
            }
            if ($request->has('keyword')) {
                $seo['keywords'][$data['key']] = trim((string) ($data['keyword'] ?? ''));
            }
            // Clés = adresses d'images (avec des points) : lues brutes, pas via validated()
            foreach ((array) $request->input('alts', []) as $src => $alt) {
                if (is_string($src) && strlen($src) < 600 && (is_string($alt) || $alt === null)) {
                    $seo['alts'][$src] = Str::limit(trim((string) $alt), 160, '');
                }
            }
        });

        return response()->json(['audit' => $audit->run($site->fresh())]);
    }

    /** Correction par l'IA : meta (titre + description d'une page), alts (images sans description), keywords (expressions clés). */
    public function fix(Request $request, string $slug, SeoAudit $audit, SeoAi $ai, AiCredits $credits)
    {
        $site = $this->site($request, $slug);
        $data = $request->validate(['type' => ['required', 'in:meta,alts,keywords'], 'key' => ['nullable', 'string', 'max:80']]);
        $admin = $request->user()->isAdmin();
        $credits->refresh($site);
        if (! $admin && $credits->balance($site) < self::COST) {
            return response()->json(['error' => 'Crédits IA épuisés : rechargez vos crédits depuis le Studio pour utiliser les corrections automatiques.'], 402);
        }
        @set_time_limit(110);
        $report = $audit->run($site);
        $done = '';
        if ($data['type'] === 'meta') {
            $page = collect($report['pages'])->firstWhere('key', $data['key'] ?? 'index');
            abort_unless($page, 404);
            $out = $ai->meta($site, $page);
            if (! $out) {
                return response()->json(['error' => 'L\'IA n\'a pas pu rédiger le titre, réessayez dans un instant.'], 502);
            }
            $this->store($site, fn (array &$seo) => $seo['meta'][$page['key']] = $out);
            $done = 'Titre et description de « '.$page['label'].' » optimisés';
        } elseif ($data['type'] === 'alts') {
            $missing = collect($report['pages'])->when(! empty($data['key']), fn ($c) => $c->where('key', $data['key']))
                ->flatMap(fn ($p) => array_column(array_filter($p['images'], fn ($i) => $i['alt'] === ''), 'src'))->unique()->values()->all();
            if (! $missing) {
                return response()->json(['audit' => $report, 'done' => 'Toutes les images sont déjà décrites']);
            }
            $out = $ai->alts($site, $missing);
            if (! $out) {
                return response()->json(['error' => 'L\'IA n\'a pas pu décrire les images, réessayez dans un instant.'], 502);
            }
            $this->store($site, function (array &$seo) use ($out) {
                foreach ($out as $src => $alt) {
                    $seo['alts'][$src] = $alt;
                }
            });
            $done = count($out).' image(s) décrite(s)'.(count($missing) > count($out) ? ' — relancez pour les suivantes' : '');
        } else {
            $out = $ai->keywords($site, $report['pages']);
            if (! $out['pages']) {
                return response()->json(['error' => 'L\'IA n\'a pas pu proposer d\'expressions, réessayez dans un instant.'], 502);
            }
            $this->store($site, function (array &$seo) use ($out) {
                $seo['suggestions'] = $out + ['at' => now()->toIso8601String()];
                foreach ($out['pages'] as $p) {
                    if (trim((string) ($seo['keywords'][$p['key']] ?? '')) === '') {
                        $seo['keywords'][$p['key']] = $p['keyword']; // jamais d'écrasement d'un choix du client
                    }
                }
            });
            $done = 'Expressions clés proposées';
        }
        if (! $admin) {
            $credits->charge($site->fresh(), self::COST, ['message' => 'SEO : '.$data['type'], 'applied' => [$done]]);
        }
        $site = $site->fresh();

        return response()->json(['audit' => $audit->run($site), 'done' => $done, 'credits' => $this->credits($site, $request->user()), 'suggestions' => $site->site_data['seo']['suggestions'] ?? null]);
    }

    private function store(Site $site, callable $fn): void
    {
        $site->refresh();
        $d = $site->site_data ?? [];
        $seo = (array) ($d['seo'] ?? []);
        $fn($seo);
        $d['seo'] = $seo;
        $site->forceFill(['site_data' => $d])->save();
        if (in_array($site->status, ['paid', 'published'], true)) {
            app(SiteRenderer::class)->publish($site->fresh());
        }
    }

    private function validKey(Site $site, string $key): void
    {
        abort_unless($key === 'index' || collect(app(SiteRenderer::class)->pages($site))->contains('slug', $key), 422, 'Page inconnue.');
    }

    private function credits(Site $site, $user): array
    {
        return ['balance' => app(AiCredits::class)->balance($site), 'free' => $user->isAdmin()];
    }

    private function site(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user->isAdmin() || ($site->user_id && $site->user_id === $user->id) || ($site->owner_email && Str::lower($site->owner_email) === Str::lower($user->email)), 403);

        return $site;
    }
}
