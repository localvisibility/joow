<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert les images uploadées depuis l'éditeur (stockage applicatif),
 * utilisées par les sites générés : https://app.joow.fr/media/{slug}/{file}
 */
class MediaController extends Controller
{
    public function show(string $slug, string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]+$/', $slug) && preg_match('/^[A-Za-z0-9._-]+$/', $file), 404);

        $path = "uploads/$slug/$file";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
