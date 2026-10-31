<?php

namespace Tests\Feature\Public;

use App\Services\Ads\AdCreative;
use App\Services\Gemini;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiImageTest extends TestCase
{
    public function test_openai_is_used_first_and_falls_back_to_previous_model(): void
    {
        config(['services.openai.key' => 'test', 'services.gemini.key' => '']);
        $img = imagecreatetruecolor(1024, 1536);
        ob_start(); imagepng($img); $png = ob_get_clean();
        Http::fake(function ($req) use ($png) {
            if (($req->data()['model'] ?? '') === 'gpt-image-2') {
                return Http::response(['error' => ['message' => 'The model gpt-image-2 does not exist']], 404);
            }

            return Http::response(['data' => [['b64_json' => base64_encode($png)]]]);
        });
        $bytes = AdCreative::drawWith('Pub', '4:5', [], 'test');
        $this->assertNotNull($bytes);
        $this->assertSame('gpt-image-1.5', Gemini::$lastImageModel);
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertSame([1024, 1280], [$w, $h]); // recadré en 4:5
    }
}
