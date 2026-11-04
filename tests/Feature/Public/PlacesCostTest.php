<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlacesCostTest extends TestCase
{
    public function test_typing_never_triggers_paid_text_search_and_results_are_cached(): void
    {
        config(['services.google_places.key' => 'k', 'services.google_places.text_daily_cap' => 2]);
        Http::fake([
            '*places:autocomplete' => Http::response(['suggestions' => []]),
            '*places:searchText' => Http::response(['places' => [['id' => 'ChIJx', 'displayName' => ['text' => 'Garage Martin'], 'formattedAddress' => 'Toulouse, France', 'types' => ['car_repair']]]]),
        ]);
        $this->getJson('/recherche?q=garage+auto+toulouse')->assertOk()->assertJsonPath('results', []);
        $this->getJson('/recherche?q=garage+auto+toulouse')->assertOk();
        Http::assertSentCount(1); // 2e frappe identique : cache, et jamais de recherche textuelle

        $this->getJson('/recherche?q=garage+auto+toulouse&full=1')->assertOk()->assertJsonPath('results.0.main', 'Garage Martin');
        $this->getJson('/recherche?q=garage+auto+toulouse&full=1')->assertOk();
        Http::assertSentCount(2); // recherche élargie une seule fois, puis cache

        // Coupe-circuit quotidien
        $this->getJson('/recherche?q=plombier+lyon&full=1')->assertOk();
        $this->getJson('/recherche?q=coiffeur+nantes&full=1')->assertOk()->assertJsonPath('results', []);
    }
}
