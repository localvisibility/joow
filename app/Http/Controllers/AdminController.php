<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\DomainRequest;
use App\Models\GenerationJob;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use App\Services\MetaConversions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Back-office Joow : indicateurs SaaS (MRR, revenus, conversion, churn), abonnements gérables,
 * clients (connexion en tant que), sites, factures, domaines et consommation IA.
 * Accès réservé aux emails listés dans JOOW_ADMIN_EMAILS.
 */
class AdminController extends Controller
{
    private string $like = '%%';

    public const TABS = ['overview', 'subscriptions', 'clients', 'sites', 'invoices', 'domains', 'ai'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';
        $q = trim((string) $request->query('q', ''));
        $this->like = '%'.mb_strtolower($q).'%';
        $days = max(7, min(365, (int) $request->query('days', 30)));

        return Inertia::render('Admin/Index', [
            'tab' => $tab, 'q' => $q, 'days' => $days,
            'kpis' => $this->kpis($days),
            'data' => match ($tab) {
                'overview' => $this->overview($days),
                'subscriptions' => $this->subscriptions($q),
                'clients' => $this->clients($q),
                'sites' => $this->sites($q, (string) $request->query('status', ''), (string) $request->query('f', '')),
                'invoices' => $this->invoices($q),
                'domains' => $this->domains(),
                'ai' => $this->ai($days),
            },
            'impersonating' => (bool) $request->session()->get('admin_impersonator'),
        ]);
    }

    // ───────────────────────────── indicateurs ─────────────────────────────

    private function price(string $plan): float
    {
        return (float) (MetaConversions::PRICES[$plan] ?? 0);
    }

    /** Indicateurs de tête, calculés sur la base (sources de vérité : statuts tenus à jour par les webhooks Stripe). */
    private function kpis(int $days): array
    {
        $since = now()->subDays($days);
        $paid = Site::whereIn('status', ['paid', 'published'])->get(['id', 'hosting_plan', 'subscription_status', 'paid_at', 'updated_at']);
        $pro = $paid->where('hosting_plan', 'pro');
        $activePro = $pro->whereIn('subscription_status', ['active', 'trialing'])->count();
        $pastDue = $pro->where('subscription_status', 'past_due')->count();
        $canceledPeriod = $pro->where('subscription_status', 'canceled')->filter(fn ($s) => $s->updated_at >= $since)->count();
        $liberte = $paid->where('hosting_plan', 'liberte');
        $mrr = $activePro * $this->price('pro');
        $startPro = max(1, $activePro + $canceledPeriod - $pro->filter(fn ($s) => $s->paid_at && $s->paid_at >= $since)->count());
        $revenuePeriod = (float) Invoice::where('status', 'paid')->where('issued_at', '>=', $since)->sum('price_ht');
        $newPaid = $paid->filter(fn ($s) => $s->paid_at && $s->paid_at >= $since)->count();
        $generated = Site::where('created_at', '>=', $since)->count();

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'active_pro' => $activePro,
            'past_due' => $pastDue,
            'liberte_total' => $liberte->count(),
            'liberte_revenue' => $liberte->count() * $this->price('liberte'),
            'revenue_period' => round($revenuePeriod, 2),
            'new_paid' => $newPaid,
            'generated' => $generated,
            'conversion' => $generated ? round($newPaid / $generated * 100, 1) : 0,
            'churn' => round($canceledPeriod / $startPro * 100, 1),
            'canceled_period' => $canceledPeriod,
            'clients' => User::count(),
            'arpu' => $activePro ? round($mrr / $activePro, 2) : 0,
        ];
    }

    /** Vue d'ensemble : courbes quotidiennes, entonnoir, canaux d'acquisition. */
    private function overview(int $days): array
    {
        $since = now()->subDays($days)->startOfDay();
        $sites = Site::where('created_at', '>=', $since)->get(['id', 'created_at', 'paid_at', 'status', 'owner_email', 'user_id', 'source', 'sector', 'hosting_plan', 'site_data']);
        $paidInPeriod = Site::whereNotNull('paid_at')->where('paid_at', '>=', $since)->get(['paid_at', 'hosting_plan']);

        $series = [];
        for ($d = $since->copy(); $d <= now(); $d->addDay()) {
            $k = $d->toDateString();
            $series[$k] = ['date' => $k, 'generated' => 0, 'paid' => 0, 'revenue' => 0.0];
        }
        foreach ($sites as $s) {
            $k = $s->created_at->toDateString();
            if (isset($series[$k])) {
                $series[$k]['generated']++;
            }
        }
        foreach ($paidInPeriod as $s) {
            $k = $s->paid_at->toDateString();
            if (isset($series[$k])) {
                $series[$k]['paid']++;
                $series[$k]['revenue'] += $this->price($s->hosting_plan ?: 'pro');
            }
        }

        $withEmail = $sites->filter(fn ($s) => $s->owner_email || $s->user_id)->count();
        $checkout = $sites->filter(fn ($s) => ! empty($s->site_data['emails']['checkout_started_at']))->count();
        $studio = $sites->filter(fn ($s) => ! empty($s->site_data['activity']['studio']))->count();
        $viewed = $sites->filter(fn ($s) => ! empty($s->site_data['activity']['preview']) || ! empty($s->site_data['activity']['fullpage']))->count();
        $paid = $sites->whereNotNull('paid_at')->count();
        $funnel = [
            ['label' => 'Sites générés', 'n' => $sites->count()],
            ['label' => 'Site consulté', 'n' => $viewed],
            ['label' => 'Email laissé', 'n' => $withEmail],
            ['label' => 'Studio ouvert', 'n' => $studio],
            ['label' => 'Paiement ouvert', 'n' => $checkout],
            ['label' => 'Payés', 'n' => $paid],
        ];

        $group = function (callable $key) use ($sites) {
            return $sites->groupBy($key)->map(fn ($g, $k) => [
                'key' => $k ?: '—', 'generated' => $g->count(),
                'emails' => $g->filter(fn ($s) => $s->owner_email || $s->user_id)->count(),
                'paid' => $g->whereNotNull('paid_at')->count(),
                'revenue' => $g->whereNotNull('paid_at')->sum(fn ($s) => $this->price($s->hosting_plan ?: 'pro')),
            ])->sortByDesc('generated')->values()->take(15)->all();
        };

        return [
            'series' => array_values($series),
            'funnel' => $funnel,
            'by_campaign' => $group(fn ($s) => $s->site_data['attribution']['utm_campaign'] ?? ($s->site_data['attribution']['utm_source'] ?? 'direct')),
            'by_source' => $group(fn ($s) => $s->source === 'joow-public' ? 'fiche Google' : ($s->source ?: 'fiche Google')),
            'by_sector' => $group(fn ($s) => config("sectors.{$s->sector}.label") ?? $s->sector),
            'plans' => [
                'pro' => Site::whereIn('status', ['paid', 'published'])->where('hosting_plan', 'pro')->count(),
                'liberte' => Site::whereIn('status', ['paid', 'published'])->where('hosting_plan', 'liberte')->count(),
            ],
        ];
    }

    private function siteRow(Site $s): array
    {
        $a = $s->site_data['attribution'] ?? [];

        return [
            'id' => $s->id, 'slug' => $s->slug, 'name' => $s->name, 'status' => $s->status,
            'sector' => config("sectors.{$s->sector}.label") ?? $s->sector, 'source' => $s->source,
            'plan' => $s->hosting_plan, 'sub' => $s->subscription_status,
            'email' => $s->owner_email ?? $s->user?->email, 'user_id' => $s->user_id,
            'created_at' => optional($s->created_at)->toIso8601String(), 'paid_at' => optional($s->paid_at)->toIso8601String(),
            'period_end' => optional($s->subscription_period_end)->toIso8601String(),
            'domain' => $s->custom_domain, 'campaign' => $a['utm_campaign'] ?? ($a['utm_source'] ?? null),
            'url' => 'https://'.$s->slug.'.joow.fr', 'mrr' => $s->hosting_plan === 'pro' && in_array($s->subscription_status, ['active', 'trialing'], true) ? $this->price('pro') : 0,
            'stripe' => $s->user?->stripe_id ? 'https://dashboard.stripe.com/customers/'.$s->user->stripe_id : null,
            'activity' => (array) ($s->site_data['activity'] ?? []),
            'checkout_at' => $s->site_data['emails']['checkout_started_at'] ?? null,
            'messages' => array_slice((array) ($s->site_data['emails']['admin_messages'] ?? []), -5),
            'owner_name' => $s->owner_name,
            'error' => $s->status === 'failed' ? Str::limit((string) GenerationJob::where('site_slug', $s->slug)->latest()->value('error'), 300) : null,
        ];
    }

    private function subscriptions(string $q): array
    {
        $rows = Site::with('user')->whereIn('status', ['paid', 'published'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('lower(name) like ?', [$this->like])->orWhereRaw('lower(slug) like ?', [$this->like])->orWhereRaw('lower(owner_email) like ?', [$this->like])))
            ->orderByDesc('paid_at')->take(500)->get();

        return ['rows' => $rows->map(fn ($s) => $this->siteRow($s) + ['cancel_at_end' => (bool) $s->user?->subscription('hosting')?->onGracePeriod()])->all()];
    }

    private function clients(string $q): array
    {
        $users = User::withCount('sites')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('lower(email) like ?', [$this->like])->orWhereRaw('lower(name) like ?', [$this->like])))
            ->orderByDesc('created_at')->take(300)->get();
        $paid = Site::whereIn('status', ['paid', 'published'])->whereIn('user_id', $users->pluck('id'))->get(['user_id', 'hosting_plan', 'subscription_status'])->groupBy('user_id');

        return ['rows' => $users->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'source' => $u->source ?? null,
            'created_at' => optional($u->created_at)->toIso8601String(), 'sites' => $u->sites_count,
            'paid' => ($paid[$u->id] ?? collect())->count(),
            'mrr' => ($paid[$u->id] ?? collect())->filter(fn ($s) => $s->hosting_plan === 'pro' && in_array($s->subscription_status, ['active', 'trialing'], true))->count() * $this->price('pro'),
            'google' => ! empty($u->google_id), 'optout' => ! empty($u->marketing_optout_at), 'admin' => $u->isAdmin(),
        ])->all()];
    }

    private function sites(string $q, string $status, string $f = ''): array
    {
        $rows = Site::with('user')
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($f === 'email', fn ($w) => $w->where(fn ($x) => $x->whereNotNull('owner_email')->orWhereNotNull('user_id')))
            ->when($f === 'noemail', fn ($w) => $w->whereNull('owner_email')->whereNull('user_id'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('lower(name) like ?', [$this->like])->orWhereRaw('lower(slug) like ?', [$this->like])->orWhereRaw('lower(owner_email) like ?', [$this->like])))
            ->orderByDesc('created_at')->take(300)->get()
            ->when($f === 'studio', fn ($c) => $c->filter(fn ($s) => ! empty($s->site_data['activity']['studio'])))
            ->when($f === 'checkout', fn ($c) => $c->filter(fn ($s) => ! empty($s->site_data['emails']['checkout_started_at'])));

        return ['rows' => $rows->map(fn ($s) => $this->siteRow($s))->values()->all(), 'statuses' => Site::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')];
    }

    private function invoices(string $q): array
    {
        $rows = Invoice::query()->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('lower(client_email) like ?', [$this->like])->orWhereRaw('lower(invoice_number) like ?', [$this->like])))
            ->orderByDesc('issued_at')->take(300)->get();

        return ['rows' => $rows->map(fn ($i) => ['id' => $i->id, 'number' => $i->invoice_number, 'email' => $i->client_email, 'label' => $i->module_name, 'ht' => (float) $i->price_ht, 'ttc' => $i->amount_ttc, 'status' => $i->status, 'issued_at' => optional($i->issued_at)->toIso8601String(), 'site' => $i->site_slug])->all(),
            'total_ht' => round((float) $rows->where('status', 'paid')->sum('price_ht'), 2)];
    }

    private function domains(): array
    {
        return ['rows' => DomainRequest::orderByDesc('created_at')->take(200)->get()->map(fn ($d) => ['domain' => $d->domain, 'site' => $d->site_slug, 'type' => $d->type, 'status' => $d->status, 'email' => $d->contact_email, 'notes' => $d->notes, 'created_at' => optional($d->created_at)->toIso8601String()])->all(),
            'custom' => Site::whereNotNull('custom_domain')->get(['slug', 'custom_domain', 'domain_source', 'domain_ssl_active', 'domain_expires_at'])->map(fn ($s) => ['site' => $s->slug, 'domain' => $s->custom_domain, 'source' => $s->domain_source, 'ssl' => (bool) $s->domain_ssl_active, 'expires' => optional($s->domain_expires_at)->toDateString(), 'renewal' => $s->site_data['domain_renewal'] ?? null])->all()];
    }

    private function ai(int $days): array
    {
        $since = now()->subDays($days);
        $jobs = GenerationJob::where('created_at', '>=', $since)->get(['status', 'result', 'created_at', 'error']);
        $totals = $jobs->map(fn ($j) => (float) data_get($j->result, 'timing.total', 0))->filter()->values();
        $credits = CreditTransaction::where('created_at', '>=', $since)->get(['delta', 'reason']);

        return [
            'generations' => $jobs->count(),
            'failed' => $jobs->where('status', 'failed')->count(),
            'avg_seconds' => $totals->count() ? round($totals->avg(), 1) : null,
            'p90_seconds' => $totals->count() ? round($totals->sort()->values()[(int) floor(0.9 * ($totals->count() - 1))], 1) : null,
            'credits_used' => -$credits->where('delta', '<', 0)->sum('delta'),
            'credits_sold' => $credits->where('reason', 'purchase')->sum('delta'),
            'by_reason' => $credits->groupBy('reason')->map(fn ($g) => $g->sum('delta'))->all(),
            'recent_errors' => $jobs->where('status', 'failed')->sortByDesc('created_at')->take(10)->map(fn ($j) => ['at' => $j->created_at->toIso8601String(), 'error' => Str::limit((string) $j->error, 200)])->values()->all(),
            'cost_estimate' => round($jobs->count() * 0.03, 2), // ≈ 0,03 € d'IA par génération (estimation)
        ];
    }

    // ───────────────────────────── actions ─────────────────────────────

    /** Abonnement : résilier en fin de période, réactiver, ou résilier immédiatement. */
    public function subscription(Request $request, Site $site)
    {
        $data = $request->validate(['action' => ['required', 'in:cancel,resume,cancel_now']]);
        $sub = $site->user?->subscription('hosting');
        abort_unless($sub, 422, 'Aucun abonnement Stripe pour ce client.');
        try {
            match ($data['action']) {
                'cancel' => $sub->cancel(),
                'resume' => $sub->resume(),
                'cancel_now' => $sub->cancelNow(),
            };
        } catch (\Throwable $e) {
            Log::warning("Admin abonnement {$site->slug} ({$data['action']}) : ".$e->getMessage());

            return back()->withErrors(['action' => 'Stripe a refusé l\'opération : '.$e->getMessage()]);
        }
        if ($data['action'] === 'cancel_now') {
            $site->forceFill(['subscription_status' => 'canceled'])->save();
            app(\App\Services\Domains\DomainRenewal::class)->onCanceled($site->fresh());
        }
        Log::info("Admin {$request->user()->email} : abonnement {$site->slug} → {$data['action']}");

        return back()->with('status', ['cancel' => 'Résiliation programmée en fin de période.', 'resume' => 'Abonnement réactivé.', 'cancel_now' => 'Abonnement résilié immédiatement.'][$data['action']]);
    }

    /** Supprime un aperçu non payé (jamais un site payé). */
    public function destroySite(Request $request, Site $site)
    {
        abort_if(in_array($site->status, ['paid', 'published'], true), 422, 'Un site payé ne se supprime pas depuis ici : résiliez d\'abord l\'abonnement.');
        $slug = $site->slug;
        $site->delete();
        Log::info("Admin {$request->user()->email} : aperçu $slug supprimé");

        return back()->with('status', "Aperçu $slug supprimé.");
    }

    /** Email personnel au prospect (ou client) d'un site, avec lien direct vers son Studio, son aperçu ou la mise en ligne. */
    public function email(Request $request, Site $site)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'link' => ['nullable', 'in:none,studio,preview,hosting'],
        ]);
        $to = $site->owner_email ?: $site->user?->email;
        abort_unless($to, 422, 'Ce site n\'a pas d\'email.');
        $accounts = app(\App\Services\Accounts::class);
        // Compte du prospect (créé sans événement publicitaire si besoin) : nécessaire aux liens de connexion
        $user = User::firstOrCreate(['email' => strtolower($to)], ['name' => $site->owner_name ?: Str::title(Str::before($to, '@')), 'password' => bcrypt(Str::random(40))]);
        Site::claimFor($user, [$site->slug]);
        abort_if($user->marketing_optout_at, 422, 'Cette personne s\'est désinscrite des emails Joow.');

        $name = trim((string) ($site->owner_name ?: $user->name));
        $vars = ['{prenom}' => $name ? Str::before($name, ' ') : '', '{site}' => (string) $site->name, '{ville}' => (string) $site->city];
        $fill = fn ($t) => trim(preg_replace('/ +([,.!?])/', '$1', strtr($t, $vars)));
        $cta = match ($data['link'] ?? 'none') {
            'studio' => ['label' => 'Ouvrir mon site dans le Studio', 'url' => $accounts->magicLink($user, '/sites/'.$site->slug.'/editeur', 14)],
            'preview' => ['label' => 'Voir mon site', 'url' => route('public.site', $site->slug)],
            'hosting' => ['label' => 'Mettre mon site en ligne', 'url' => $accounts->magicLink($user, '/hebergement', 14)],
            default => null,
        };

        \Illuminate\Support\Facades\Mail::send('emails.notification', [
            'title' => $fill($data['subject']), 'intro' => $fill($data['message']), 'rows' => [], 'cta' => $cta,
            'note' => 'Vous pouvez répondre directement à cet email.',
            'brand' => ['name' => 'Joow', 'accent' => '#6366f1', 'url' => 'https://joow.fr', 'phone' => null, 'address' => null, 'email' => null],
        ], fn ($m) => $m->to($to)->replyTo($request->user()->email)->subject($fill($data['subject'])));

        $sd = $site->site_data ?? [];
        $sd['emails']['admin_messages'][] = ['at' => now()->toIso8601String(), 'subject' => $fill($data['subject']), 'by' => $request->user()->email, 'link' => $data['link'] ?? 'none'];
        $site->timestamps = false;
        $site->forceFill(['site_data' => $sd])->saveQuietly();
        Log::info("Admin {$request->user()->email} : email « {$data['subject']} » envoyé à $to ({$site->slug})");

        return back()->with('status', "Email envoyé à $to.");
    }

    /** Se connecter en tant que client (support), avec retour à son propre compte. */
    public function impersonate(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 422, 'Impossible de se connecter en tant qu\'un autre administrateur.');
        $request->session()->put('admin_impersonator', $request->user()->id);
        Log::info("Admin {$request->user()->email} se connecte en tant que {$user->email}");
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function stopImpersonating(Request $request)
    {
        $id = $request->session()->pull('admin_impersonator');
        abort_unless($id && ($admin = User::find($id)) && $admin->isAdmin(), 403);
        Auth::login($admin);

        return redirect()->route('admin.index', ['tab' => 'clients']);
    }
}
