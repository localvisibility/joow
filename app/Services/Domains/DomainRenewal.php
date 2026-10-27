<?php

namespace App\Services\Domains;

use App\Models\Site;
use App\Services\Accounts;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Domaine inclus et abonnement : à la résiliation de la formule Pro, le renouvellement automatique
 * du domaine acheté par Joow est coupé chez Hostinger (le domaine reste actif jusqu'à son échéance) ;
 * si le client se réabonne avant l'échéance, il est réactivé.
 */
class DomainRenewal
{
    public function __construct(private HostingerClient $hostinger) {}

    public function onCanceled(Site $site): bool
    {
        return $this->apply($site, false);
    }

    public function onReactivated(Site $site): bool
    {
        return ($site->site_data['domain_renewal'] ?? null) === 'disabled' && $this->apply($site, true);
    }

    private function apply(Site $site, bool $enable): bool
    {
        if ($site->domain_source !== 'hostinger' || ! $site->custom_domain || ! $this->hostinger->enabled()) {
            return false;
        }
        $domain = $site->custom_domain;
        try {
            $sub = $this->hostinger->domainSubscription($domain);
            if (! $sub || empty($sub['id'])) {
                throw new \RuntimeException('abonnement Hostinger introuvable');
            }
            $this->hostinger->setAutoRenewal((string) $sub['id'], $enable);
        } catch (\Throwable $e) {
            Log::error("Renouvellement $domain (".($enable ? 'réactivation' : 'arrêt').') : '.$e->getMessage());
            $this->alertAdmin($site, $enable, $e->getMessage());

            return false;
        }
        $d = $site->site_data ?? [];
        $d['domain_renewal'] = $enable ? 'enabled' : 'disabled';
        $site->forceFill(['site_data' => $d])->save();
        Log::info("Renouvellement automatique de $domain ".($enable ? 'réactivé' : 'coupé'));

        if (! $enable && $site->user) {
            $until = $site->domain_expires_at?->translatedFormat('j F Y') ?? 'son échéance';
            app(Accounts::class)->send($site->user, "Votre abonnement est résilié : votre domaine $domain", [
                'preheader' => "Le domaine reste à vous jusqu'au $until.",
                'title' => 'Votre domaine reste actif jusqu\'au '.$until,
                'intro' => "Votre abonnement Joow est résilié. Le domaine $domain qui était inclus reste enregistré jusqu'au $until, mais il ne sera pas renouvelé par Joow.",
                'bullets' => [
                    ['🔁', 'Vous changez d\'avis ?', 'Réabonnez-vous avant cette date depuis votre espace : le domaine est conservé et renouvelé automatiquement.'],
                    ['📦', 'Vous voulez le garder ailleurs ?', 'Répondez à cet email : nous vous transmettons le code de transfert pour le déplacer chez le registrar de votre choix.'],
                ],
                'note' => 'Sans action de votre part, le domaine expirera à cette date et pourra être enregistré par un tiers.',
            ]);
        }

        return true;
    }

    private function alertAdmin(Site $site, bool $enable, string $error): void
    {
        try {
            Mail::raw("Action manuelle requise : ".($enable ? 'réactiver' : 'couper')." le renouvellement automatique du domaine {$site->custom_domain} (site {$site->slug}) dans hPanel → Facturation → Abonnements.\nErreur : $error", function ($m) use ($site) {
                $m->to(config('mail.from.address'))->subject("Domaine {$site->custom_domain} : renouvellement à ajuster");
            });
        } catch (\Throwable) {
        }
    }
}
