<?php

namespace App\Http\Controllers;

use App\Services\PlacesPhoto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Sert une photo Google Places via Joow (clé API jamais exposée), récupérée une fois puis conservée. */
class PlacesPhotoController extends Controller
{
    public function show(string $token)
    {
        $d = PlacesPhoto::decode($token);
        abort_unless($d, 404);
        [$name, $w] = $d;
        $path = 'gphoto/'.md5($name.'|'.$w).'.jpg';
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $key = (string) config('services.google_places.key');
            abort_unless($key !== '', 404);
            $res = Http::timeout(15)->get("https://places.googleapis.com/v1/$name/media", ['maxWidthPx' => $w, 'key' => $key]);
            abort_unless($res->successful() && strlen($res->body()) > 500, 404);
            $disk->put($path, $res->body());
        }

        return response($disk->get($path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=2592000, immutable']);
    }
}
