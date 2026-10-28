<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\ImageStore;
use App\Services\StockPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageBankTest extends TestCase
{
    use RefreshDatabase;

    private function setUpSite(): array
    {
        $user = User::create(['name' => 'T', 'email' => 't@exemple.fr', 'password' => bcrypt('secret-123')]);
        $site = Site::create(['slug' => 'fournil-'.fake()->lexify('????'), 'name' => 'Fournil', 'sector' => 'boulangerie', 'status' => 'preview', 'source' => 'joow-public', 'user_id' => $user->id,
            'site_data' => ['content' => ['hero_title' => 'Le pain qui se mérite', 'services' => [['name' => 'Pains au levain', 'desc' => 'Cuits au four à sole']]]]]);

        return [$user, $site];
    }

    public function test_search_uses_context_of_the_photo_slot_and_returns_results(): void
    {
        [$user, $site] = $this->setUpSite();
        $this->mock(StockPhotos::class, function ($m) {
            $m->shouldReceive('smartQueries')->withArgs(fn ($label, $place, $subject, $q) => str_contains($place, 'prestation') && str_contains($subject, 'levain') && $q === '')
                ->andReturn(['queries' => ['sourdough bread loaves'], 'query_fr' => 'pains au levain', 'suggestions' => ['fournil', 'viennoiseries']]);
            $m->shouldReceive('library')->andReturn([['url' => 'https://images.unsplash.com/photo-1?w=1920', 'thumb' => 'https://images.unsplash.com/photo-1?w=400', 'alt' => 'bread', 'credit' => 'Photo : A / Unsplash', 'provider' => 'unsplash', 'download' => 'https://api.unsplash.com/photos/1/download', 'query' => 'x']]);
        });
        $this->actingAs($user)->getJson(route('sites.editor.bank', $site->slug).'?path=content.services.0.image')
            ->assertOk()->assertJsonPath('query_fr', 'pains au levain')->assertJsonPath('results.0.provider', 'unsplash')->assertJsonPath('suggestions.1', 'viennoiseries');
    }

    public function test_choosing_a_photo_copies_it_tracks_unsplash_and_keeps_the_credit_but_refuses_other_hosts(): void
    {
        [$user, $site] = $this->setUpSite();
        $this->mock(ImageStore::class, fn ($m) => $m->shouldReceive('mirror')->andReturn(['https://images.unsplash.com/photo-1?w=1920' => 'https://app.joow.fr/media/x/abc.jpg']));
        $this->mock(StockPhotos::class, fn ($m) => $m->shouldReceive('trackUse')->once()->with('https://api.unsplash.com/photos/1/download'));
        $this->actingAs($user)->postJson(route('sites.editor.bank.use', $site->slug), ['url' => 'https://images.unsplash.com/photo-1?w=1920', 'download' => 'https://api.unsplash.com/photos/1/download', 'credit' => 'Photo : A / Unsplash'])
            ->assertOk()->assertJsonPath('url', 'https://app.joow.fr/media/x/abc.jpg');
        $this->assertSame('Photo : A / Unsplash', collect($site->fresh()->site_data['photo_credits'])->first()['credit']);
        $this->actingAs($user)->postJson(route('sites.editor.bank.use', $site->slug), ['url' => 'http://169.254.169.254/latest/meta-data'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('sites.editor.bank.use', $site->slug), ['url' => 'https://evil.example/x.jpg'])->assertStatus(422);
    }
}
