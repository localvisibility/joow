<?php

namespace App\Services\Domains;

use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Domaines personnalisés des sites.
 *
 * L'application ne touche pas à nginx ni aux certificats : elle publie la liste
 * des domaines dans <SITES_PATH>/_joow/domains.json ; un agent sur l'hôte
 * (deploy/domains/joow-domains.sh) vérifie le DNS, obtient les certificats
 * Let's Encrypt, met à jour nginx et écrit <SITES_PATH>/_joow/status.json,
 * que l'application relit pour afficher l'état au client.
 *
 * Statuts : pending_dns → dns_ok → active ; registering (achat en cours) ; error.
 */
class DomainManager
{
    public const RESERVED_SUFFIXES = ['.joow.fr', '.localhost', '.local', '.test'];

    /** IP publique du serveur (celle vers laquelle les clients pointent leur domaine). */
    public function serverIp(): ?string
    {
        if ($ip = config('joow.domains.server_ip')) {
            return $ip;
        }

        return Cache::remember('joow:server_ip', now()->addHour(), function () {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'app.joow.fr';
            $ip = gethostbyname($host);

            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : null;
        });
    }

    /** Normalise et valide un domaine (apex, sans www). Renvoie null si invalide. */
    public function normalize(string $domain): ?string
    {
        $d = strtolower(trim($domain));
        $d = preg_replace('~^https?://~', '', $d);
        $d = rtrim(explode('/', $d)[0], '.');
        $d = preg_replace('/^www\./', '', $d);
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $d)) {
            $d = idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $d;
        }
        if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $d)) {
            return null;
        }
        foreach (self::RESERVED_SUFFIXES as $s) {
            if (Str::endsWith($d, $s) || $d === ltrim($s, '.')) {
                return null;
            }
        }

        return $d;
    }

    /** Enregistrements DNS à créer chez le registrar du client. */
    public function instructions(Site $site): array
    {
        $ip = $this->serverIp() ?? '—';

        return [
            ['type' => 'A', 'name' => '@', 'value' => $ip, 'label' => $site->custom_domain],
            ['type' => 'CNAME', 'name' => 'www', 'value' => $site->slug.'.joow.fr', 'label' => 'www.'.$site->custom_domain],
        ];
    }

    /** Rattache un domaine (possédé ou acheté) au site. */
    public function attach(Site $site, string $domain, string $source = 'own', array $extra = []): Site
    {
        $d = $this->normalize($domain);
        abort_unless($d, 422, 'Nom de domaine invalide.');
        $taken = Site::where('custom_domain', $d)->where('id', '!=', $site->id)->exists();
        abort_if($taken, 422, 'Ce domaine est déjà utilisé par un autre site.');

        $site->forceFill(array_merge([
            'custom_domain'       => $d,
            'domain_source'       => $source,
            'domain_status'       => $source === 'hostinger' ? 'registering' : 'pending_dns',
            'domain_verified'     => false,
            'domain_ssl_active'   => false,
            'domain_www_ok'       => false,
            'domain_error'        => null,
            'domain_checked_at'   => null,
            'domain_activated_at' => null,
        ], $extra))->save();

        $this->export();

        return $site;
    }

    public function detach(Site $site): void
    {
        $site->forceFill([
            'custom_domain' => null, 'domain_source' => null, 'domain_status' => null, 'domain_verified' => false,
            'domain_ssl_active' => false, 'domain_www_ok' => false, 'domain_error' => null, 'domain_order_id' => null,
        ])->save();
        $this->export();
        app(SiteRenderer::class)->publish($site);
    }

    /** Vérifie le DNS du domaine (apex et www) et met à jour le statut. */
    public function checkDns(Site $site): Site
    {
        $d = $site->custom_domain;
        $ip = $this->serverIp();
        if (! $d || ! $ip) {
            return $site;
        }

        $apexOk = in_array($ip, $this->resolveA($d), true);
        $wwwOk = in_array($ip, $this->resolveA('www.'.$d), true);

        // Domaine acheté chez nous : attendre l'enregistrement au registre, puis (re)pointer le DNS nous-mêmes
        if (! $apexOk && $site->domain_source === 'hostinger') {
            return $this->followPurchase($site, $ip, $wwwOk);
        }

        $status = $site->domain_status;
        if ($site->domain_ssl_active && $apexOk) {
            $status = 'active';
        } elseif ($apexOk) {
            $status = $status === 'registering' && ! $site->domain_verified ? 'dns_ok' : ($status === 'active' ? 'active' : 'dns_ok');
        } elseif ($status !== 'registering') {
            $status = 'pending_dns';
        }

        $site->forceFill([
            'domain_verified'   => $apexOk,
            'domain_www_ok'     => $wwwOk,
            'domain_status'     => $status,
            'domain_checked_at' => now(),
        ])->save();

        return $site;
    }

    /**
     * Domaine commandé chez Hostinger mais pas encore servi : tant que le registre ne le connaît pas,
     * on reste en « commande en cours » ; ensuite on pose la zone DNS (réessayée toutes les 10 min).
     */
    private function followPurchase(Site $site, string $ip, bool $wwwOk): Site
    {
        $d = $site->custom_domain;
        $registered = $this->registered($d);
        $patch = ['domain_verified' => false, 'domain_www_ok' => $wwwOk, 'domain_checked_at' => now()];

        if ($registered === false) {
            $patch['domain_status'] = 'registering';
            $patch['domain_error'] = null;
            // Toujours inconnu du registre 3 h après la commande : paiement Hostinger en attente ou dossier bloqué → prévenir l'équipe
            $data = $site->site_data ?? [];
            $orderedAt = $this->orderedAt($site);
            if (empty($data['domain_alert_sent']) && $orderedAt && $orderedAt->diffInHours(now()) >= 3) {
                $data['domain_alert_sent'] = now()->toIso8601String();
                $patch['site_data'] = $data;
                foreach ((array) config('joow.admins') as $admin) {
                    try {
                        \Illuminate\Support\Facades\Mail::raw("Le domaine $d (site {$site->slug}) a été commandé chez Hostinger il y a plus de 3 h mais le registre ne le connaît toujours pas.\nVérifiez la commande dans hPanel (paiement en attente, dossier .fr incomplet…).", fn ($m) => $m->to($admin)->subject("Joow · domaine $d toujours pas enregistré"));
                    } catch (\Throwable) {
                    }
                }
            }
        } else {
            $patch['domain_status'] = 'pending_dns';
            $patch['domain_error'] = null;
            if (\Illuminate\Support\Facades\Cache::add("joow:domain-point:$d", 1, now()->addMinutes(10))) {
                try {
                    $ok = app(HostingerClient::class)->pointToServer($d, $ip, $site->slug);
                    Log::info("DNS $d ".($ok ? 'pointé' : 'non pointé (zone pas encore prête)')." vers $ip");
                } catch (\Throwable $e) {
                    Log::warning("DNS $d : ".$e->getMessage());
                }
            }
        }
        $site->forceFill($patch)->save();

        return $site;
    }

    private function orderedAt(Site $site): ?\Carbon\CarbonInterface
    {
        $at = \App\Models\DomainRequest::where('site_id', $site->id)->where('domain', $site->custom_domain)->where('type', 'order')->latest()->value('created_at');

        return $at ? \Carbon\Carbon::parse($at) : null;
    }

    /** Le registre connaît-il le domaine ? (RDAP ; null si on ne sait pas). Mis en cache 5 min. */
    public function registered(string $domain): ?bool
    {
        return \Illuminate\Support\Facades\Cache::remember("joow:rdap:$domain", now()->addMinutes(5), function () use ($domain) {
            $tld = substr(strrchr($domain, '.'), 1);
            $url = $tld === 'fr' ? "https://rdap.nic.fr/domain/$domain" : "https://rdap.org/domain/$domain";
            try {
                $res = \Illuminate\Support\Facades\Http::timeout(8)->withOptions(['allow_redirects' => true])->get($url);
                if ($res->status() === 404) {
                    return false;
                }

                return $res->successful() ? true : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /** Résolution A (via le résolveur système), sans exception. */
    public function resolveA(string $host): array
    {
        try {
            $recs = @dns_get_record($host, DNS_A) ?: [];
            $ips = array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? null, $recs)));
            if (! $ips) {
                $ip = gethostbyname($host);
                $ips = $ip !== $host ? [$ip] : [];
            }

            return $ips;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Publie la liste des domaines pour l'agent hôte. */
    public function export(): void
    {
        $dir = $this->dir();
        $rows = Site::whereNotNull('custom_domain')->whereIn('status', ['paid', 'published'])
            ->get(['slug', 'custom_domain', 'domain_status', 'domain_www_ok'])
            ->map(fn ($s) => ['domain' => $s->custom_domain, 'slug' => $s->slug, 'www' => (bool) $s->domain_www_ok, 'status' => $s->domain_status])
            ->values()->all();
        $this->writeJson($dir.'/domains.json', ['generated_at' => now()->toIso8601String(), 'server_ip' => $this->serverIp(), 'domains' => $rows]);
    }

    /** Relit l'état écrit par l'agent (certificats) et met à jour les sites. */
    public function importStatus(): int
    {
        $file = $this->dir().'/status.json';
        if (! is_file($file)) {
            return 0;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        $n = 0;
        foreach ((array) ($data['domains'] ?? []) as $domain => $st) {
            $site = Site::where('custom_domain', $domain)->first();
            if (! $site) {
                continue;
            }
            $ssl = (bool) ($st['ssl'] ?? false);
            $err = $st['error'] ?? null;
            $patch = ['domain_ssl_active' => $ssl, 'domain_error' => $ssl ? null : ($err ?: $site->domain_error)];
            if ($ssl && $site->domain_status !== 'active') {
                $patch['domain_status'] = 'active';
                $patch['domain_activated_at'] = now();
                $patch['domain_verified'] = true;
                $n++;
                $site->forceFill($patch)->save();
                // Republie pour injecter la redirection <slug>.joow.fr → domaine (canonical)
                try {
                    app(SiteRenderer::class)->publish($site);
                } catch (\Throwable $e) {
                    Log::warning("Republication après activation du domaine {$domain} : ".$e->getMessage());
                }
                continue;
            }
            // « DNS ne pointe pas encore » = attente normale, pas une panne
            if (! $ssl && $err && $site->domain_status !== 'active' && $site->domain_verified) {
                $patch['domain_status'] = 'error';
            } elseif (! $ssl && ! $site->domain_verified) {
                $patch['domain_error'] = null;
            }
            $site->forceFill($patch)->save();
        }

        return $n;
    }

    /** Cycle complet (planifié chaque minute) : DNS des domaines en attente, état des certificats, export. */
    public function sync(): array
    {
        $checked = 0;
        Site::whereNotNull('custom_domain')->whereIn('status', ['paid', 'published'])
            ->where(fn ($q) => $q->whereNull('domain_status')->orWhere('domain_status', '!=', 'active'))
            ->where(fn ($q) => $q->whereNull('domain_checked_at')->orWhere('domain_checked_at', '<', now()->subMinutes(2)))
            ->limit(25)->get()
            ->each(function (Site $s) use (&$checked) {
                $this->checkDns($s);
                $checked++;
            });
        $activated = $this->importStatus();
        $this->export();

        return ['checked' => $checked, 'activated' => $activated];
    }

    public function state(Site $site): array
    {
        return [
            'domain'       => $site->custom_domain,
            'source'       => $site->domain_source,
            'status'       => $site->domain_status,
            'dns_ok'       => (bool) $site->domain_verified,
            'www_ok'       => (bool) $site->domain_www_ok,
            'ssl'          => (bool) $site->domain_ssl_active,
            'error'        => $site->domain_error,
            'checked_at'   => optional($site->domain_checked_at)->diffForHumans(),
            'activated_at' => optional($site->domain_activated_at)->toDateString(),
            'expires_at'   => optional($site->domain_expires_at)->toDateString(),
            'server_ip'    => $this->serverIp(),
            'instructions' => $site->custom_domain ? $this->instructions($site) : [],
            'url'          => $site->custom_domain ? 'https://'.$site->custom_domain : null,
        ];
    }

    private function dir(): string
    {
        $dir = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/_joow';
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
            @chmod($dir, 0777);
        }

        return $dir;
    }

    private function writeJson(string $file, array $data): void
    {
        $tmp = $file.'.'.uniqid('', true).'.tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            Log::warning("Domaines : écriture impossible de $file");

            return;
        }
        @chmod($tmp, 0666);
        @rename($tmp, $file);
    }
}
