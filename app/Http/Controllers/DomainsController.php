<?php

namespace App\Http\Controllers;

use App\Models\DomainRequest;
use App\Models\Site;
use App\Services\Domains\DomainManager;
use App\Services\Domains\HostingerClient;
use Illuminate\Http\Request;
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
        abort_unless($this->domainIncluded($site), 422, 'Un domaine a déjà été commandé pour ce site.');
        $data = $request->validate(['domain' => ['required', 'string', 'max:120']]);

        $domain = $dm->normalize($data['domain']);
        abort_unless($domain, 422, 'Nom de domaine invalide.');

        $tld = substr(strrchr($domain, '.'), 1);
        $price = $hostinger->prices()[$tld] ?? null;
        abort_unless($price, 422, "L'extension .$tld n'est pas proposée.");

        // Sécurité : re-vérifier la disponibilité juste avant l'achat
        $avail = collect($hostinger->search(substr($domain, 0, -strlen($tld) - 1), [$tld]))->firstWhere('domain', $domain);
        abort_unless($avail && $avail['available'], 422, 'Ce domaine n\'est plus disponible.');

        try {
            $order = $hostinger->purchase($domain, $price['item_id']);
        } catch (\Throwable $e) {
            Log::error("Achat domaine $domain : ".$e->getMessage());

            return response()->json(['error' => 'L\'achat n\'a pas pu être finalisé. Aucun montant n\'a été prélevé de votre côté ; réessayez ou contactez-nous.'], 502);
        }

        $dm->attach($site, $domain, 'hostinger', ['domain_order_id' => (string) ($order['id'] ?? ''), 'domain_expires_at' => now()->addYear()]);
        DomainRequest::create(['site_id' => $site->id, 'site_slug' => $slug, 'domain' => $domain, 'type' => 'order', 'status' => 'in_progress', 'contact_email' => $request->user()->email, 'notes' => 'Hostinger order '.($order['id'] ?? '?').' · '.($order['status'] ?? '')]);

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

    // ───────────────────────────── helpers ─────────────────────────────

    private function payload(Site $site, DomainManager $dm, HostingerClient $hostinger): array
    {
        return $dm->state($site) + [
            'paid'          => in_array($site->status, ['paid', 'published'], true),
            'plan'          => $site->hosting_plan,
            'live_url'      => 'https://'.$site->slug.'.joow.fr',
            'purchase_enabled' => $hostinger->enabled(),
            'included'      => $this->domainIncluded($site),
        ];
    }

    /** Le premier domaine acheté est inclus dans la formule. */
    private function domainIncluded(Site $site): bool
    {
        return in_array($site->status, ['paid', 'published'], true)
            && ! DomainRequest::where('site_slug', $site->slug)->where('type', 'order')->whereIn('status', ['in_progress', 'active'])->exists();
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
