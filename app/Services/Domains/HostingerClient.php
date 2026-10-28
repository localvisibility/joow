<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * API Hostinger (https://developers.hostinger.com) : disponibilité et achat de
 * domaines, zone DNS. Le domaine est acheté sur le compte Joow (moyen de paiement
 * par défaut), puis pointé vers le serveur.
 */
class HostingerClient
{
    public const BASE = 'https://developers.hostinger.com';

    public const TLDS = ['fr', 'com', 'net', 'eu', 'org', 'paris', 'shop', 'pro'];

    public function enabled(): bool
    {
        return (bool) config('services.hostinger.token');
    }

    private function http()
    {
        return Http::withToken((string) config('services.hostinger.token'))
            ->acceptJson()->timeout(40)->baseUrl(self::BASE);
    }

    /** Disponibilité d'un nom pour plusieurs extensions, avec prix annuel. */
    public function search(string $name, array $tlds = self::TLDS): array
    {
        $name = Str::lower(preg_replace('/[^a-z0-9-]/', '', Str::ascii(Str::before(trim($name), '.'))));
        if ($name === '') {
            return [];
        }
        $res = $this->http()->post('/api/domains/v1/availability', ['domain' => $name, 'tlds' => array_values($tlds), 'with_alternatives' => false]);
        $res->throw();
        $prices = $this->prices();

        return collect($res->json())->filter(fn ($r) => ! empty($r['domain']))->map(function ($r) use ($prices) {
            $tld = Str::afterLast($r['domain'], '.');
            $p = $prices[$tld] ?? null;

            return [
                'domain'      => $r['domain'],
                'tld'         => $tld,
                'available'   => (bool) ($r['is_available'] ?? false),
                'restriction' => $r['restriction'] ?? null,
                'price'       => $p['price'] ?? null,            // centimes, renouvellement
                'first_price' => $p['first_price'] ?? null,      // centimes, 1re année
                'currency'    => $p['currency'] ?? null,
                'item_id'     => $p['item_id'] ?? null,
            ];
        })->values()->all();
    }

    /** Prix catalogue des domaines (1 an) par extension, mis en cache (jamais un résultat vide). */
    public function prices(): array
    {
        $cached = Cache::get('hostinger:domain-prices:v2');
        if (is_array($cached) && $cached) {
            return $cached;
        }
        $res = $this->http()->get('/api/billing/v1/catalog', ['category' => 'DOMAIN']);
        if (! $res->ok()) {
            Log::warning('Hostinger catalogue : HTTP '.$res->status().' '.Str::limit($res->body(), 300));

            return [];
        }
        $out = [];
        foreach ((array) $res->json() as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tld = $this->tldOf($item);
            if (! $tld || isset($out[$tld])) {
                continue;
            }
            $best = null;
            foreach ($item['prices'] ?? [] as $price) {
                $id = (string) ($price['id'] ?? '');
                if ($id === '' || Str::contains($id, ['transfer', 'renew', 'trade'])) {
                    continue;
                }
                $years = ($price['period_unit'] ?? '') === 'year' ? (int) ($price['period'] ?? 0) : (($price['period_unit'] ?? '') === 'month' ? (int) ($price['period'] ?? 0) / 12 : 0);
                if ($years === 1 || $years === 1.0) {
                    $best = $price;
                    break;
                }
                $best ??= $price; // à défaut, la première offre d'enregistrement
            }
            if ($best) {
                $out[$tld] = ['item_id' => $best['id'], 'price' => $best['price'] ?? null, 'first_price' => $best['first_period_price'] ?? ($best['price'] ?? null), 'currency' => $best['currency'] ?? null];
            }
        }
        if ($out) {
            Cache::put('hostinger:domain-prices:v2', $out, now()->addHours(12));
        } else {
            Log::warning('Hostinger catalogue : aucune extension reconnue. Exemple : '.Str::limit(json_encode(array_slice((array) $res->json(), 0, 2)), 800));
        }

        return $out;
    }

    /** Extension d'un article du catalogue : métadonnées, puis nom (« .fr », « Domaine .fr »), puis identifiant (« …-domain-fr »). */
    private function tldOf(array $item): ?string
    {
        foreach ([data_get($item, 'metadata.tld'), data_get($item, 'metadata.extension'), data_get($item, 'metadata.domain_extension')] as $v) {
            if (is_string($v) && $v !== '') {
                return Str::lower(ltrim(trim($v), '.'));
            }
        }
        if (preg_match('/\.([a-z]{2,24}(?:\.[a-z]{2,3})?)\b/i', (string) ($item['name'] ?? ''), $m)) {
            return Str::lower($m[1]);
        }
        if (preg_match('/domain-([a-z]{2,24})$/i', (string) ($item['id'] ?? ''), $m)) {
            return Str::lower($m[1]);
        }

        return null;
    }

    /** Achète le domaine (moyen de paiement par défaut du compte, WHOIS par défaut). */
    public function purchase(string $domain, string $itemId): array
    {
        $body = ['domain' => $domain, 'item_id' => $itemId];
        if ($pm = config('services.hostinger.payment_method_id')) {
            $body['payment_method_id'] = (int) $pm;
        }
        if ($w = config('services.hostinger.whois_id')) {
            $body['domain_contacts'] = ['owner_id' => (int) $w, 'admin_id' => (int) $w, 'billing_id' => (int) $w, 'tech_id' => (int) $w];
        }
        $res = $this->http()->post('/api/domains/v1/portfolio', $body);
        $res->throw();

        return $res->json();
    }

    /** Abonnement Hostinger correspondant à un domaine (le nom de l'abonnement contient le domaine). */
    public function domainSubscription(string $domain): ?array
    {
        $res = $this->http()->get('/api/billing/v1/subscriptions');
        $res->throw();
        foreach ((array) $res->json() as $sub) {
            if (is_array($sub) && str_contains(strtolower((string) ($sub['name'] ?? '')), strtolower($domain))) {
                return $sub;
            }
        }

        return null;
    }

    /** Active ou coupe le renouvellement automatique d'un abonnement Hostinger. */
    public function setAutoRenewal(string $subscriptionId, bool $enabled): void
    {
        $path = '/api/billing/v1/subscriptions/'.rawurlencode($subscriptionId).'/auto-renewal/'.($enabled ? 'enable' : 'disable');
        ($enabled ? $this->http()->patch($path) : $this->http()->delete($path))->throw();
    }

    public function details(string $domain): ?array
    {
        $res = $this->http()->get('/api/domains/v1/portfolio/'.$domain);

        return $res->ok() ? $res->json() : null;
    }

    /** Pointe le domaine vers le serveur : A @ → IP, CNAME www → <slug>.joow.fr. */
    public function pointToServer(string $domain, string $ip, string $slug): bool
    {
        $res = $this->http()->put('/api/dns/v1/zones/'.$domain, [
            'overwrite' => true,
            'zone' => [
                ['name' => '@', 'type' => 'A', 'ttl' => 3600, 'records' => [['content' => $ip]]],
                ['name' => 'www', 'type' => 'CNAME', 'ttl' => 3600, 'records' => [['content' => $slug.'.joow.fr.']]],
            ],
        ]);

        return $res->successful();
    }
}
