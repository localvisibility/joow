<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client Gemini partagé : bascule automatiquement sur un modèle de repli quand le modèle
 * principal est saturé (HTTP 503 « high demand ») ou limité (429), avec une courte attente.
 */
class Gemini
{
    /** Ordre d'essai : alias « latest » d'abord, puis versions stables et le modèle léger. */
    public const MODELS = ['gemini-flash-latest', 'gemini-2.5-flash', 'gemini-flash-lite-latest', 'gemini-2.5-flash-lite'];

    public static function enabled(): bool
    {
        return (string) config('services.gemini.key') !== '';
    }

    /**
     * Appelle generateContent sur le premier modèle disponible.
     *
     * @param  array  $body  contents + generationConfig
     * @return Response|null  dernière réponse (réussie ou non), null si aucune requête n'a abouti
     */
    public static function generate(array $body, int $timeout = 60, string $context = 'gemini'): ?Response
    {
        $key = (string) config('services.gemini.key');
        $last = null;
        foreach (self::MODELS as $i => $model) {
            try {
                $res = Http::timeout($timeout)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                    $body
                );
            } catch (\Throwable $e) {
                Log::warning("$context : $model exception ".$e->getMessage());
                continue;
            }
            $last = $res;
            if ($res->successful()) {
                if ($i > 0) {
                    Log::info("$context : réponse obtenue via le modèle de repli $model");
                }

                return $res;
            }
            // 503 (saturation) / 429 (quota) / 500 : on passe au modèle suivant après une courte pause
            if (in_array($res->status(), [429, 500, 503], true)) {
                Log::warning("$context : $model HTTP {$res->status()} ".($res->json('error.message') ?? ''));
                usleep(700_000);
                continue;
            }

            // Autre erreur (400 clé/prompt invalide, 404 modèle inconnu…) : inutile d'insister sur les autres modèles sauf 404
            Log::warning("$context : $model HTTP {$res->status()} ".($res->json('error.message') ?? ''));
            if ($res->status() !== 404) {
                return $res;
            }
        }

        return $last;
    }

    /** Texte de la première candidate, ou chaîne vide. */
    public static function text(?Response $res): string
    {
        return $res ? trim((string) data_get($res->json(), 'candidates.0.content.parts.0.text')) : '';
    }
}
