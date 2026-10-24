<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Déduit le métier d'un établissement depuis son nom, son type principal Google et ses types.
 * 1) score par mots-clés du catalogue (config/sectors.php, clé `keywords`) ;
 * 2) si le résultat est absent ou ambigu, classification par l'IA parmi les métiers du catalogue.
 */
class SectorDetector
{
    /** Types Google trop génériques pour départager (ignorés dans le score). */
    private const GENERIC = ['point_of_interest', 'establishment', 'store', 'food', 'health', 'finance', 'service', 'place_of_worship'];

    public function detect(string $name, array $types = [], array $context = []): string
    {
        $sectors = config('sectors', []);
        $scores = $this->scores($name, $types, $sectors);
        arsort($scores);
        $ranked = array_keys($scores);
        $best = $ranked[0] ?? 'service';
        $bestScore = $scores[$best] ?? 0;
        $second = $scores[$ranked[1] ?? ''] ?? 0;

        // Résultat net : signal suffisant et sans égalité
        if ($bestScore >= 2 && $bestScore > $second) {
            return $best;
        }
        if ($bestScore === 1 && $second === 0 && ! Gemini::enabled()) {
            return $best;
        }

        // Ambigu ou inconnu : l'IA tranche parmi le catalogue
        $ai = $this->classify($name, $types, $context, array_keys($sectors));
        if ($ai) {
            return $ai;
        }

        return $bestScore > 0 ? $best : 'service';
    }

    /** @return array<string,int> secteur => score */
    private function scores(string $name, array $types, array $sectors): array
    {
        $norm = fn ($s) => Str::of((string) $s)->lower()->ascii()->toString();
        $hayName = $norm($name);
        $hayTypes = $norm(implode(' ', array_diff($types, self::GENERIC)));
        $primary = in_array($types[0] ?? '', self::GENERIC, true) ? '' : $norm($types[0] ?? '');

        $scores = [];
        foreach ($sectors as $key => $cfg) {
            $score = 0;
            foreach (array_unique(array_map($norm, $cfg['keywords'] ?? [])) as $w) {
                if ($w === '') {
                    continue;
                }
                if (str_contains($hayName, $w)) {
                    $score += 2;             // le nom est le signal le plus fiable
                }
                if ($primary !== '' && str_contains($primary, $w)) {
                    $score += 3;             // type principal Google
                } elseif (str_contains($hayTypes, $w)) {
                    $score += 1;
                }
            }
            $scores[$key] = $score;
        }

        return $scores;
    }

    /** Classification IA parmi les clés du catalogue (réponse JSON {"sector": "..."}). */
    private function classify(string $name, array $types, array $context, array $keys): ?string
    {
        if (! Gemini::enabled()) {
            return null;
        }
        $catalog = collect(config('sectors'))->map(fn ($c, $k) => "$k = {$c['label']}")->implode("\n");
        $extra = collect([
            'Type principal Google' => $context['primary_type'] ?? null,
            'Types Google' => implode(', ', $types),
            'Adresse' => $context['address'] ?? null,
            'Extraits d\'avis' => collect($context['reviews'] ?? [])->take(3)->map(fn ($r) => Str::limit((string) ($r['text'] ?? ''), 160))->implode(' | '),
        ])->filter()->map(fn ($v, $k) => "$k : $v")->implode("\n");

        $prompt = <<<TXT
Classe cet établissement français dans UN métier du catalogue ci-dessous. Réponds uniquement en JSON : {"sector": "<clé>"}.
Choisis la clé la plus précise ; « service » seulement si rien ne correspond.

Établissement : {$name}
{$extra}

CATALOGUE (clé = libellé) :
{$catalog}
TXT;
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 64, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 20, 'SectorDetector', fast: true);
            $key = data_get(json_decode(Gemini::text($res), true), 'sector');

            return is_string($key) && in_array($key, $keys, true) ? $key : null;
        } catch (\Throwable $e) {
            Log::warning('SectorDetector : '.$e->getMessage());

            return null;
        }
    }
}
