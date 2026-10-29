<?php

namespace App\Http\Controllers;

use App\Models\DomainRequest;
use App\Models\Site;
use App\Services\Domains\DomainManager;
use App\Services\Domains\HostingerClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Nom de domaine d'un site : connexion d'un domaine existant (pointage DNS),
 * recherche et achat d'un nouveau domaine (Hostinger), suivi DNS / certificat.
 */
class DomainsController extends Controller
{
    public function state(Request $request, string $slug, DomainManager $dm, HostingerClient $hostinger)
    {
        $site = $this->site($request, $slug);
        if ($site->custom_domain && $site->domain_status !== 'active' && (! $site->domain_checked_at || $site->domain_checked_at->lt(now()->subSeconds(30)))) {
            $dm->checkDns($site);
        }

        return response()->json($this->payload($site, $dm, $hostinger));
    }

    /** Domaine déjà possédé : on l'enregistre et on donne les enregistrements DNS à créer. */
    public function connect(Request $request, string $slug, DomainManager $dm, HostingerClient $hostinger)
    {
        $site = $this->site($request, $slug);
        $this->requirePaid($site);
        $data = $request->validate(['domain' => ['required', 'string', 'max:120']]);

        $dm->attach($site, $data['domain'], 'own');
        DomainRequest::create(['site_id' => $site->id, 'site_slug' => $slug, 'domain' => $site->custom_domain, 'type' => 'connect', 'status' => 'in_progress', 'contact_email' => $request->user()->email]);
        $dm->checkDns($site);

        return response()->json($this->payload($site->fresh(), $dm, $hostinger));
    }

    public function check(Request $request, string $slug, DomainManager $dm, HostingerClient $hostinger)
    {
        $site = $this->site($request, $slug);
        abort_unless($site->custom_domain, 422, 'Aucun domaine à vérifier.');
        $dm->checkDns($site);
        $dm->importStatus();
        $dm->export();

        return response()->json($this->payload($site->fresh(), $dm, $hostinger));
    }

    public function remove(Request $request, string $slug, DomainManager $dm, HostingerClient $hostinger)
    {
        $site = $this->site($request, $slug);
        abort_if($site->domain_source === 'hostinger' && $site->domain_status !== 'error', 422, 'Ce domaine a été acheté pour ce site : contactez le support pour le modifier.');
        $dm->detach($site);

        return response()->json($this->payload($site->fresh(), $dm, $hostinger));
    }

    /** Recherche de disponibilité (Hostinger). */
    public function search(Request $request, string $slug, HostingerClient $hostinger)
    {
        $site = $this->site($request, $slug);
        abort_unless($hostinger->enabled(), 503, 'L\'achat de domaine n\'est pas encore disponible.');
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:63']]);

        try {
            $results = $hostinger->search($data['q']);
        } catch (\Throwable $e) {
            Log::warning('Hostinger availability : '.$e->getMessage());

            return response()->json(['error' => 'Recherche indisponible pour le moment.'], 502);
        }

        return response()->json(['results' => $results, 'included' => $this->domainIncluded($site)]);
    }

    /** Achat du domaine (inclus dans la formule pour le premier domaine). */
    public function order(Request $request, string $slug, DomainManager $dm, HostingerClient $hostinger)
    {
        $site = $this->site($request, $slug);
        $this->requirePaid($site);
        abort_unless($hostinger->enabled(), 503, 'L\'achat de domaine n\'est pas encore disponible.');
        if ($t = $this->trialEndsAt($site)) {
            abort(422, 'Le domaine inclus sera disponible à la fin de votre essai gratuit, le '.$t->format('d/m/Y').'. En attendant, vous pouvez connecter un domaine que vous possédez déjà.');
        }
        abort_if(in_array($site->subscription_status, ['past_due', 'canceled', 'unpaid'], true), 422, 'Votre abonnement doit être à jour pour commander le domaine inclus.');
        abort_unless($this->domainIncluded($site), 422, 'Un domaine a déjà été commandé pour ce site.');
        $data = $request->validate(['domain' => ['required', 'string', 'max:120']]);

        // Un seul achat à la fois par site : un double clic ou deux onglets ne commandent jamais deux domaines
        $lock = Cache::lock("joow:domain-order:{$site->id}", 120);
        abort_unless($lock->get(), 409, 'Une commande de domaine est déjà en cours pour ce site.');
        try {
            return $this->placeOrder($request, $site, $data['domain'], $dm, $hostinger);
        } finally {
            $lock->release();
        }
    }

    private function placeOrder(Request $request, Site $site, string $input, DomainManager $dm, HostingerClient $hostinger)
    {
        $slug = $site->slug;
        abort_unless($this->domainIncluded($site->fresh()), 422, 'Un domaine a déjà été commandé pour ce site.');
        $domain = $dm->normalize($input);
        abort_unless($domain, 422, 'Nom de domaine invalide.');

        $tld = substr(strrchr($domain, '.'), 1);
        $price = $hostinger->prices()[$tld] ?? null;
        abort_unless($price, 422, "L'extension .$tld n'est pas proposée.");

        // Sécurité : re-vérifier la disponibilité juste avant l'achat
        $avail = collect($hostinger->search(substr($domain, 0, -strlen($tld) - 1), [$tld]))->firstWhere('domain', $domain);
        abort_unless($avail && $avail['available'], 422, 'Ce domaine n\'est plus disponible.');

        // Le domaine inclus est réservé AVANT l'achat : en cas d'échec réseau ambigu, aucun second achat n'est possible
        $req = DomainRequest::create(['site_id' => $site->id, 'site_slug' => $slug, 'domain' => $domain, 'type' => 'order', 'status' => 'in_progress', 'contact_email' => $request->user()->email, 'notes' => 'Commande en cours']);
        try {
            $order = $hostinger->purchase($domain, $price['item_id']);
        } catch (\Throwable $e) {
            $why = HostingerClient::errorMessage($e);
            Log::error("Achat domaine $domain : $why");
            foreach ((array) config('joow.admins') as $admin) {
                try {
                    \Illuminate\Support\Facades\Mail::raw("Achat du domaine $domain (site $slug, client {$request->user()->email}) en échec.\n\nRéponse Hostinger : $why\n\nBack-office : ".route('admin.index', ['tab' => 'domains']), fn ($m) => $m->to($admin)->subject("Joow · achat de domaine en échec : $domain"));
                } catch (\Throwable) {
                }
            }
            // Refus explicite d'Hostinger (4xx) : rien n'a été acheté, le client peut réessayer. Sinon on garde la réservation et on vérifie à la main.
            $refused = method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 500;
            $req->update(['status' => $refused ? 'failed' : 'in_progress', 'notes' => ($refused ? 'Refusé : ' : 'À vérifier (réponse incertaine) : ').\Illuminate\Support\Str::limit($why, 480)]);

            return response()->json(['error' => $refused ? 'L\'enregistrement de ce domaine n\'a pas abouti et rien n\'a été prélevé. Notre équipe est prévenue et revient vers vous ; vous pouvez aussi essayer une autre extension.' : 'La commande est en cours de vérification. Nous revenons vers vous rapidement, inutile de recommencer.'], 502);
        }

        $dm->attach($site, $domain, 'hostinger', ['domain_order_id' => (string) ($order['id'] ?? ''), 'domain_expires_at' => now()->addYear()]);
        $req->update(['notes' => 'Hostinger order '.($order['id'] ?? '?').' · '.($order['status'] ?? '')]);

        // Pointage DNS (la zone existe dès l'achat chez Hostinger ; réessayé par le sync si trop tôt)
        if ($ip = $dm->serverIp()) {
            try {
                $hostinger->pointToServer($domain, $ip, $site->slug);
            } catch (\Throwable $e) {
                Log::warning("DNS $domain : ".$e->getMessage());
            }
        }

        return response()->json($this->payload($site->fresh(), $dm, $hostinger));
    }

    /**
     * Le client ne veut pas attendre la fin de l'essai : on termine l'essai maintenant,
     * Stripe facture le premier mois immédiatement, et le domaine inclus se débloque.
     */
    public function startSubscription(Request $request, string $slug, DomainManager $dm, HostingerClient $hostinger, \App\Services\Subscription $subscription)
    {
        $site = $this->site($request, $slug);
        $this->requirePaid($site);
        abort_unless($site->hosting_plan === 'pro' && $site->user, 422, 'Cette action concerne la formule Pro en période d\'essai.');

        try {
            $r = $subscription->startNow($site);
        } catch (\Throwable $e) {
            Log::error("Fin d'essai anticipée ($slug) : ".$e->getMessage());

            return response()->json(['error' => 'Impossible de démarrer l\'abonnement maintenant. Réessayez ou contactez-nous.'], 502);
        }

        return response()->json($this->payload($site->fresh(), $dm, $hostinger) + ['started' => true, 'invoice_paid' => $r['paid'], 'pay_url' => $r['pay_url']]);
    }

    // ───────────────────────────── helpers ─────────────────────────────

    private function payload(Site $site, DomainManager $dm, HostingerClient $hostinger): array
    {
        $trialEnd = $this->trialEndsAt($site);

        return $dm->state($site) + [
            'paid'          => in_array($site->status, ['paid', 'published'], true),
            'plan'          => $site->hosting_plan,
            'live_url'      => 'https://'.$site->slug.'.joow.fr',
            'purchase_enabled' => $hostinger->enabled(),
            'included'      => $this->domainIncluded($site),
            // Formule Pro en période d'essai : l'achat du domaine inclus attend le premier paiement
            'trial_ends_at' => $trialEnd?->toDateString(),
        ];
    }

    /**
     * Le premier domaine acheté est inclus dans la formule — une fois l'abonnement
     * réellement payé (pas pendant l'essai gratuit de la formule Pro).
     */
    private function domainIncluded(Site $site): bool
    {
        return in_array($site->status, ['paid', 'published'], true)
            // abonnement réellement payé : Pro actif ou Liberté ; pas d'impayé, pas de résiliation
            && in_array($site->subscription_status ?: ($site->hosting_plan === 'liberte' ? 'lifetime' : 'active'), ['active', 'lifetime'], true)
            && $this->trialEndsAt($site) === null
            && ! DomainRequest::where('site_slug', $site->slug)->where('type', 'order')->whereIn('status', ['in_progress', 'active'])->exists();
    }

    private function trialEndsAt(Site $site): ?\Carbon\Carbon
    {
        return app(\App\Services\Subscription::class)->trialEndsAt($site);
    }

    private function requirePaid(Site $site): void
    {
        abort_unless(in_array($site->status, ['paid', 'published'], true), 422, 'Mettez votre site en ligne (formule Pro ou Liberté) pour utiliser un nom de domaine.');
    }

    private function site(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        abort_unless($site->editableBy($request->user()), 403);

        return $site;
    }
}
