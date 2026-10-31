<?php

namespace App\Services\Ads;

use App\Models\Site;
use App\Services\Gemini;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Joow Ads : stratégie publicitaire, pubs prêtes à publier et variantes de pages d'offre,
 * rédigées par l'IA à partir des informations réelles de l'établissement.
 */
class AdsStudio
{
    public const GOALS = [
        'calls' => 'faire appeler l\'établissement',
        'leads' => 'recevoir des demandes de devis / contact',
        'bookings' => 'obtenir des réservations / rendez-vous',
        'visits' => 'faire venir des clients sur place',
    ];

    /** Fiche synthétique de l'établissement envoyée à l'IA. */
    public function brief(Site $site): string
    {
        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $c = $d['content'] ?? [];
        $services = collect($c['services'] ?? [])->map(fn ($s) => trim(($s['name'] ?? '').' : '.($s['desc'] ?? '').(! empty($s['price']) ? ' ('.$s['price'].')' : '')))->filter()->take(8)->implode("\n- ");

        return implode("\n", array_filter([
            'Établissement : '.$site->name,
            'Métier : '.(config("sectors.{$site->sector}.label") ?? $site->sector),
            $site->city ? 'Ville : '.$site->city : null,
            ($site->rating && $site->reviews_count) ? "Avis Google : {$site->rating}/5 sur {$site->reviews_count} avis" : null,
            ! empty($c['hero_title']) ? 'Promesse du site : '.$c['hero_title'].' — '.($c['hero_subtitle'] ?? '') : null,
            $services ? "Prestations :\n- ".$services : null,
            ! empty($c['about']) ? 'Présentation : '.Str::limit(strip_tags(is_array($c['about']) ? implode(' ', $c['about']) : (string) $c['about']), 600) : null,
        ]));
    }

    /** Photos disponibles du site (pour proposer les visuels). */
    public function photos(Site $site): array
    {
        $img = (array) ($site->site_data['images'] ?? []);

        return array_values(array_unique(array_filter(array_merge([$img['hero'] ?? null, $img['about'] ?? null], (array) ($img['gallery'] ?? []), (array) ($img['services'] ?? [])), fn ($u) => is_string($u) && $u !== '')));
    }

    /** Stratégie + pubs prêtes à publier. */
    public function pack(Site $site, string $goal, ?string $offer = null, ?int $budget = null): ?array
    {
        $photos = $this->photos($site);
        $goalTxt = self::GOALS[$goal] ?? self::GOALS['leads'];
        $prompt = <<<TXT
Tu es un expert en publicité locale (Meta et Google Ads) pour les petits commerces et artisans français.
Rédige une stratégie et des publicités prêtes à publier, en français naturel, sans jargon, sans promesse invérifiable ni superlatif creux.

{$this->brief($site)}

Objectif de la campagne : {$goalTxt}.
Offre à mettre en avant : {$this->orNone($offer)}
Budget publicitaire indicatif : {$this->orNone($budget ? $budget.' € par mois' : null)}
Nombre de photos disponibles : {$this->count($photos)} (indices 0 à {$this->maxIndex($photos)}).

Réponds UNIQUEMENT en JSON :
{
  "strategy": {
    "summary": "la stratégie en 3 phrases",
    "audiences": [{"name": "...", "targeting": "ciblage Meta concret (zone, âge, centres d'intérêt)", "why": "..."}],
    "angles": [{"name": "...", "idea": "..."}],
    "offer_ideas": ["3 idées d'offre d'appel adaptées au métier"],
    "budget": "répartition conseillée du budget et durée de test",
    "kpi": "indicateur à suivre et seuil d'alerte"
  },
  "meta_ads": [{"angle": "...", "hook": "accroche ≤ 12 mots", "primary_text": "texte principal 2-4 phrases, avec émojis sobres", "headline": "titre ≤ 6 mots", "description": "≤ 8 mots", "cta": "Appeler | En savoir plus | Réserver | Obtenir un devis", "visual": "description du visuel à utiliser", "photo": 0}],
  "google_ads": {"headlines": ["15 titres ≤ 30 caractères"], "descriptions": ["4 descriptions ≤ 90 caractères"], "keywords": ["15 mots-clés locaux"], "negative_keywords": ["8 mots-clés négatifs"]},
  "landing": {"title": "nom interne de la page", "path": "url courte en minuscules-avec-tirets",
    "variants": [{"headline": "≤ 9 mots", "subheadline": "≤ 22 mots", "offer": "l'offre en une ligne", "bullets": ["3 bénéfices courts"], "cta": "≤ 4 mots"}, {"…": "2e variante avec un angle franchement différent"}]}
}
Produis 5 meta_ads aux angles variés (preuve sociale, urgence douce, bénéfice concret, curiosité, offre). Les champs "photo" sont des indices de photo existants.
TXT;

        $data = $this->ask($prompt, 'AdsStudio.pack');
        if (! $data || empty($data['meta_ads'])) {
            return null;
        }
        $data['meta_ads'] = array_map(fn ($a) => $a + ['image' => $photos[(int) ($a['photo'] ?? 0)] ?? ($photos[0] ?? null)], array_slice((array) $data['meta_ads'], 0, 6));
        $data['photos'] = $photos;

        return $data;
    }

    /** Deux variantes de page d'offre (A/B) à partir d'une offre. */
    public function landingVariants(Site $site, string $goal, string $offer): ?array
    {
        $goalTxt = self::GOALS[$goal] ?? self::GOALS['leads'];
        $prompt = <<<TXT
Tu es un expert en pages d'atterrissage qui convertissent, pour les commerces locaux français.
{$this->brief($site)}

Objectif : {$goalTxt}. Offre : {$offer}.
Écris DEUX variantes d'une page d'offre pour un test A/B, avec des angles franchement différents
(A : bénéfice concret et rassurant ; B : preuve sociale et urgence douce). Français naturel, pas de superlatif creux.
Réponds UNIQUEMENT en JSON :
{"title": "nom interne", "path": "url-courte-avec-tirets", "variants": [{"headline": "≤ 9 mots", "subheadline": "≤ 22 mots", "offer": "l'offre en une ligne", "bullets": ["3 bénéfices courts"], "cta": "≤ 4 mots"}, {...}]}
TXT;

        return $this->ask($prompt, 'AdsStudio.landing');
    }

    private function ask(string $prompt, string $ctx): ?array
    {
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.8, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 8000, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 90, $ctx);
            $data = json_decode((string) Gemini::text($res), true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning("$ctx : ".$e->getMessage());

            return null;
        }
    }

    private function orNone(?string $v): string
    {
        return $v !== null && trim($v) !== '' ? trim($v) : 'aucune précisée (propose la plus pertinente)';
    }

    private function count(array $a): int
    {
        return count($a);
    }

    private function maxIndex(array $a): int
    {
        return max(0, count($a) - 1);
    }
}
