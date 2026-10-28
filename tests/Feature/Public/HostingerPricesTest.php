<?php

namespace Tests\Feature\Public;

use App\Services\Domains\HostingerClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingerPricesTest extends TestCase
{
    public function test_catalog_items_are_mapped_by_extension_whatever_the_naming(): void
    {
        config(['services.hostinger.token' => 't', 'cache.default' => 'array']);
        Http::fake(['*/api/billing/v1/catalog*' => Http::response([
            ['id' => 'hostingercom-domain-fr', 'name' => '.fr', 'category' => 'DOMAIN', 'metadata' => null, 'prices' => [
                ['id' => 'hostingercom-domain-fr-eur-1y', 'currency' => 'EUR', 'price' => 999, 'first_period_price' => 499, 'period' => 1, 'period_unit' => 'year'],
                ['id' => 'hostingercom-domain-fr-eur-2y', 'currency' => 'EUR', 'price' => 1998, 'first_period_price' => 1498, 'period' => 2, 'period_unit' => 'year'],
            ]],
            ['id' => 'hostingercom-domain-com', 'name' => 'Domain', 'category' => 'DOMAIN', 'metadata' => ['tld' => '.com'], 'prices' => [
                ['id' => 'hostingercom-domain-com-transfer-eur-1y', 'currency' => 'EUR', 'price' => 900, 'period' => 1, 'period_unit' => 'year'],
                ['id' => 'hostingercom-domain-com-eur-1y', 'currency' => 'EUR', 'price' => 1599, 'first_period_price' => 999, 'period' => 1, 'period_unit' => 'year'],
            ]],
            ['id' => 'hostingercom-domain-net', 'name' => 'Nom de domaine', 'category' => 'DOMAIN', 'prices' => [
                ['id' => 'hostingercom-domain-net-eur-1y', 'currency' => 'EUR', 'price' => 1499, 'period' => 12, 'period_unit' => 'month'],
            ]],
        ])]);
        $p = app(HostingerClient::class)->prices();
        $this->assertSame('hostingercom-domain-fr-eur-1y', $p['fr']['item_id']);
        $this->assertSame(499, $p['fr']['first_price']);
        $this->assertSame('hostingercom-domain-com-eur-1y', $p['com']['item_id'], 'transfert ignoré, extension lue dans les métadonnées');
        $this->assertSame('hostingercom-domain-net-eur-1y', $p['net']['item_id'], 'extension lue dans l\'identifiant, période en mois');
    }
}
