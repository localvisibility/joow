<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Cache;
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

    /** Prix catalogue des domaines (1 an) par extension, mis en cache. */
    public function prices(): array
    {
        return Cache::remember('hostinger:domain-prices', now()->addHours(12), function () {
            $res = $this->http()->get('/api/billing/v1/catalog', ['category' => 'DOMAIN']);
            if (! $res->ok()) {
                return [];
            }
            $out = [];
            foreach ($res->json() as $item) {
                $tld = Str::lower((string) (data_get($item, 'metadata.tld') ?: Str::afterLast((string) ($item['name'] ?? ''), '.')));
                $tld = ltrim($tld, '.');
                foreach ($item['prices'] ?? [] as $price) {
                    if (($price['period_unit'] ?? '') === 'year' && (int) ($price['period'] ?? 0) === 1 && ! Str::contains($price['id'] ?? '', ['transfer', 'renew'])) {
                        $out[$tld] = ['item_id' => $price['id'], 'price' => $price['price'], 'first_price' => $price['first_period_price'] ?? $price['price'], 'currency' => $price['currency']];
                        break;
                    }
                }
            }

            return $out;
        });
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
