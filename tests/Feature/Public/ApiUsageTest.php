<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Services\ApiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_outgoing_api_calls_are_counted_by_billing_sku(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'usageMetadata' => ['totalTokenCount' => 1200]])]);
        Http::withHeaders(['X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.types'])->post('https://places.googleapis.com/v1/places:searchText', []);
        Http::withHeaders(['X-Goog-FieldMask' => 'places.id'])->post('https://places.googleapis.com/v1/places:searchText', []);
        Http::withHeaders(['X-Goog-FieldMask' => 'id,displayName,rating,reviews,photos'])->get('https://places.googleapis.com/v1/places/ChIJx');
        Http::post('https://places.googleapis.com/v1/places:autocomplete', []);
        Http::post('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=x', []);
        Http::post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent?key=x', []);

        $r = collect(ApiUsage::report()['rows'])->keyBy('sku');
        $this->assertSame(1, $r['places.text_pro']['month']);
        $this->assertSame(1, $r['places.text_ids']['month']);
        $this->assertSame(1, $r['places.details_atmosphere']['month']);
        $this->assertSame(1, $r['places.autocomplete']['month']);
        $this->assertSame(1200, $r['gemini.text']['tokens']);
        $this->assertSame(1, $r['gemini.image_pro']['month']);

        // Au-delà de la gratuité, coût estimé
        ApiUsage::add('places.text_pro', 6000);
        $this->assertGreaterThan(0, collect(ApiUsage::report()['rows'])->firstWhere('sku', 'places.text_pro')['cost_eur']);

    }
}
