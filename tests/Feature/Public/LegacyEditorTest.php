<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\LegacyEditor;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Édition en place d'une page importée : seul l'élément visé change, le design reste identique. */
class LegacyEditorTest extends TestCase
{
    use RefreshDatabase;

    private string $html = '<!DOCTYPE html><html><head><title>Clos | Cassis</title><meta name="description" content="Ancienne description"><style>.x{color:red}</style></head>'
        .'<body><h1 class="t">Chambres d&#8217;hôtes à Cassis</h1><p>Piscine &amp; parking</p><script>var a="<p>pas du texte</p>";</script>'
        .'<img src="/old.jpg" srcset="/old-2x.jpg 2x" alt="Piscine"><select><option>Choix</option></select><a href="/x">Réserver</a></body></html>';

    public function test_segments_skip_code_and_edits_only_touch_the_target(): void
    {
        $ed = new LegacyEditor;
        $texts = array_map(fn ($s) => LegacyEditor::plain($s[2]), $ed->segments($this->html));
        $this->assertSame(['Chambres d’hôtes à Cassis', 'Piscine & parking', 'Réserver'], $texts);

        $new = $ed->setText($this->html, 1, 'Piscine chauffée & parking privé');
        $this->assertSame(str_replace('Piscine &amp; parking', 'Piscine chauffée &amp; parking privé', $this->html), $new);

        $img = $ed->setImage($this->html, 0, 'https://app.joow.fr/media/clos/new.jpg');
        $this->assertStringContainsString('<img src="https://app.joow.fr/media/clos/new.jpg" alt="Piscine">', $img);

        $meta = $ed->setMeta($this->html, 'Chambres d\'hôtes Cassis', 'Nouvelle description');
        $this->assertStringContainsString('<title>Chambres d&apos;hôtes Cassis</title>', $meta);
        $this->assertStringContainsString('content="Nouvelle description"', $meta);

        $inst = $ed->instrument($this->html, '<script>rt</script>');
        $this->assertStringContainsString('<span data-lvt="0">Chambres d&#8217;hôtes à Cassis</span>', $inst);
        $this->assertStringContainsString('var a="<p>pas du texte</p>"', $inst); // scripts intacts
        $this->assertStringContainsString('<img data-lvi="0" src="/old.jpg"', $inst);
    }

    public function test_real_imported_page_edit_changes_nothing_else(): void
    {
        $path = base_path('tests/fixtures/legacy-clos.html');
        if (! is_file($path)) {
            $this->markTestSkipped('Page de référence absente.');
        }
        $html = file_get_contents($path);
        $ed = new LegacyEditor;
        $segs = $ed->segments($html);
        $this->assertGreaterThan(50, count($segs));
        $k = collect($segs)->search(fn ($s) => mb_strlen(LegacyEditor::plain($s[2])) > 30);
        $new = $ed->setText($html, $k, 'Texte modifié depuis le Studio');
        // Avant et après le segment : octets strictement identiques
        $this->assertSame(substr($html, 0, $segs[$k][0]), substr($new, 0, $segs[$k][0]));
        $this->assertSame(substr($html, $segs[$k][0] + $segs[$k][1]), substr($new, strlen($new) - (strlen($html) - $segs[$k][0] - $segs[$k][1])));
        $this->assertCount(count($segs), $ed->segments($new)); // la numérotation est stable
    }

    public function test_studio_edits_publish_the_page_with_backup_and_undo(): void
    {
        $dir = sys_get_temp_dir().'/joow-led-'.uniqid();
        config(['services.sites_path' => $dir]);
        @mkdir("$dir/clos", 0777, true);
        file_put_contents("$dir/clos/index.html", $this->html);
        $user = User::factory()->create(['email' => 'proprio@yahoo.fr']);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'chambres', 'status' => 'paid', 'source' => 'legacy', 'owner_email' => 'proprio@yahoo.fr', 'site_data' => [], 'ai_credits_monthly' => 3, 'ai_credits_reset_at' => now()->addMonth()]);

        $preview = app(SiteRenderer::class)->html($site, true);
        $this->assertStringContainsString('data-lvt="1"', $preview);
        $this->assertStringNotContainsString('data-lvt', app(SiteRenderer::class)->html($site)); // le site public n'est jamais repéré

        $this->actingAs($user)->postJson(route('sites.editor.legacy', 'clos'), ['type' => 'text', 'k' => 1, 'value' => 'Piscine chauffée'])->assertOk()->assertJsonPath('backups', 1);
        $this->assertStringContainsString('<p>Piscine chauffée</p>', file_get_contents("$dir/clos/index.html"));
        $this->actingAs($user)->postJson(route('sites.editor.legacy', 'clos'), ['type' => 'text', 'k' => 1, 'value' => '   '])->assertStatus(422);

        $this->actingAs($user)->postJson(route('sites.editor.legacy.undo', 'clos'))->assertOk();
        $this->assertSame($this->html, file_get_contents("$dir/clos/index.html"));

        // IA : réécrit le texte demandé et consomme 1 crédit
        config(['services.gemini.key' => 'test']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => 'J\'ai réécrit le titre.', 'changes' => [['k' => 0, 'text' => 'Chambres d\'hôtes de charme à Cassis']]])]]]]]])]);
        $this->actingAs($user)->postJson(route('sites.editor.legacy.ai', 'clos'), ['message' => 'rends le titre plus chaleureux'])->assertOk()->assertJsonPath('applied', 1);
        $this->assertStringContainsString('<h1 class="t">Chambres d\'hôtes de charme à Cassis</h1>', file_get_contents("$dir/clos/index.html"));
        $this->assertSame(2, $site->fresh()->ai_credits_monthly);
    }
}
