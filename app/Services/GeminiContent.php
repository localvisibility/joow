<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Génère le contenu éditorial d'un site à partir de la fiche établissement.
 * Renvoie un tableau structuré (hero, about, services, faq, badges).
 */
class GeminiContent
{
    private string $key;

    public function __construct()
    {
        $this->key = (string) config('services.gemini.key');
    }

    public function generate(array $business, string $sector, string $sectorLabel): array
    {
        $fallback = $this->fallback($business, $sectorLabel);
        if (! $this->key) {
            return $fallback;
        }

        $reviews = collect($business['reviews'] ?? [])->take(4)
            ->map(fn ($r) => '- '.($r['text'] ?? ''))->implode("\n");

        $prompt = <<<TXT
Tu es rédacteur web pour des sites vitrines d'entreprises locales en France.
Rédige le contenu du site de cet établissement, en français, ton professionnel et chaleureux, orienté conversion.

Établissement : {$business['name']}
Secteur : {$sectorLabel}
Ville : {$business['city']}
Note Google : {$business['rating']} ({$business['reviews_count']} avis)
Extraits d'avis clients :
{$reviews}

Réponds UNIQUEMENT avec un JSON valide de cette forme exacte :
{
  "hero_title": "titre d'accroche court (max 8 mots)",
  "hero_subtitle": "sous-titre percutant (max 15 mots)",
  "about_p1": "1er paragraphe présentation (2-3 phrases)",
  "about_p2": "2e paragraphe (2-3 phrases)",
  "badges": ["4 points forts très courts (2-3 mots)"],
  "services": [
    {"name": "nom du service", "desc": "description courte (1 phrase)", "price": "indication tarifaire ou 'Sur devis'"}
  ],
  "faq": [
    {"q": "question fréquente", "a": "réponse (2-3 phrases)"}
  ],
  "cta_text": "phrase d'appel à l'action (1 phrase)"
}
Donne 6 services et 5 questions FAQ pertinents pour le secteur.
TXT;

        try {
            $res = Http::timeout(45)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key='.$this->key,
                [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.7, 'responseMimeType' => 'application/json'],
                ]
            );

            $text = data_get($res->json(), 'candidates.0.content.parts.0.text');
            $data = $text ? json_decode($text, true) : null;

            return is_array($data) ? array_merge($fallback, array_filter($data)) : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function fallback(array $business, string $sectorLabel): array
    {
        $city = $business['city'] ?? '';
        return [
            'hero_title'    => $business['name'],
            'hero_subtitle' => "{$sectorLabel} à {$city}",
            'about_p1'      => "{$business['name']} vous accompagne à {$city} avec professionnalisme et proximité.",
            'about_p2'      => 'Un interlocuteur unique, des tarifs clairs et un suivi attentif à chaque étape.',
            'badges'        => ['Devis gratuit', 'Réponse rapide', 'Travail soigné', 'Proximité'],
            'services'      => [],
            'faq'           => [],
            'cta_text'      => 'Contactez-nous pour un premier échange gratuit.',
        ];
    }
}
