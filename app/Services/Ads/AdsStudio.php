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

    /** Contenu commun d'une page d'offre (format JSON demandé à l'IA). */
    public const CONTENT_SCHEMA = <<<'JSON'
"content": {
  "form": {"title": "titre du formulaire (≤ 6 mots)", "subtitle": "≤ 12 mots, rassurant",
    "steps": [{"key": "identifiant_court", "question": "question simple (≤ 10 mots)", "type": "choice | multi | text | number", "options": ["3 à 6 réponses courtes, pour choice / multi"], "required": true}],
    "ask_email": false, "ask_postcode": true, "button": "texte du bouton final (≤ 4 mots)", "success": "message de confirmation (≤ 20 mots)"},
  "process": [{"title": "étape (≤ 4 mots)", "text": "≤ 15 mots"}],
  "faq": [{"q": "objection fréquente du client", "a": "réponse rassurante 1-2 phrases"}],
  "guarantees": ["4 réassurances courtes (≤ 4 mots) vraies pour tout professionnel sérieux : devis gratuit, sans engagement, réponse rapide…"],
  "urgency": "phrase d'urgence douce et honnête (≤ 12 mots), ou vide",
  "theme": "premium (sombre, haut de gamme) | warm (chaleureux, crème) | bold (blanc, contrasté, énergique)",
  "widget": {"type": "savings | estimate | none", "title": "titre de l'outil (≤ 6 mots)",
    "savings": {"label": "ce que le visiteur indique, ex. Votre facture de chauffage annuelle", "unit": "€ / an", "min": 500, "max": 5000, "default": 1800, "pct_min": 0, "pct_max": 0, "note": "mention de prudence"},
    "estimate": {"question": "critère principal du prix", "options": ["3 à 5 choix"]}}
}
JSON;

    public const CONTENT_RULES = 'Outil interactif : « savings » (simulateur d\'économies) pour l\'énergie, l\'isolation, le solaire, les pompes à chaleur, avec des pourcentages d\'économie prudents et habituels du marché (sinon 0) ; « estimate » (fourchette de prix) quand le prix dépend d\'un critère simple ; « none » sinon. Ne mets JAMAIS de prix : c\'est le professionnel qui les renseigne. Formulaire : 2 à 4 questions de qualification utiles au professionnel pour préparer son devis ou son rappel (type de besoin, logement / surface, délai, budget…), en choix cliquables de préférence ; les coordonnées (nom, téléphone) sont demandées automatiquement à la fin. 3 étapes « comment ça marche ». 4 questions FAQ qui lèvent les objections (prix, délai, engagement, qualité). N\'invente AUCUNE certification, label, chiffre de clients ou distinction : uniquement des réassurances génériques vraies.';

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
    "variants": [{"headline": "≤ 9 mots", "subheadline": "≤ 22 mots", "offer": "l'offre en une ligne", "bullets": ["3 bénéfices courts"], "cta": "≤ 4 mots"}, {"…": "2e variante avec un angle franchement différent"}],
    {$this->contentSchema()}}
}
Page d'offre : {$this->contentRules()}
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

    public const LAYOUTS = ['impact', 'offre', 'avis', 'chiffre', 'probleme', 'question', 'ugc'];

    /**
     * Campagne créative : un concept par axe (idée, scène à photographier, accroche, mise en page)
     * + un carrousel narratif. Les images sont produites ensuite (photo réelle ou image IA).
     */
    public function creatives(Site $site, array $pack): ?array
    {
        $photos = $this->photos($site);
        $angles = collect($pack['strategy']['angles'] ?? [])->map(fn ($a) => '- '.($a['name'] ?? '').' : '.($a['idea'] ?? ''))->implode("\n");
        $hooks = collect($pack['meta_ads'] ?? [])->map(fn ($a) => '- '.($a['hook'] ?? ''))->implode("\n");
        $layouts = implode(', ', self::LAYOUTS);
        $reviews = collect($site->site_data['business']['reviews'] ?? [])->filter(fn ($r) => ($r['rating'] ?? 5) >= 4 && strlen($r['text'] ?? '') > 30)->take(3)
            ->map(fn ($r, $i) => "[$i] « ".Str::limit($r['text'], 200).' » — '.($r['author'] ?? ''))->implode("\n");
        $prompt = <<<TXT
Tu es directeur artistique d'une agence de publicité Meta spécialisée dans les commerces et artisans locaux.
{$this->brief($site)}

Stratégie retenue : {$this->orNone($pack['strategy']['summary'] ?? null)}
Angles :
{$angles}
Accroches déjà écrites :
{$hooks}
Avis Google disponibles (à citer tels quels, jamais inventés) :
{$this->orNone($reviews ?: null)}
Photos réelles disponibles : {$this->count($photos)} (indices 0 à {$this->maxIndex($photos)}).

Conçois une campagne de 5 visuels publicitaires, un par axe différent, qui arrêtent le défilement sur mobile.
Règles : texte sur l'image très court (accroche ≤ 6 mots, sous-titre ≤ 10 mots), un seul message par visuel,
français naturel, aucune promesse invérifiable, aucun chiffre inventé (un chiffre seulement s'il vient des informations ci-dessus ou d'une offre).
Mises en page possibles : {$layouts}
(impact = photo plein cadre + accroche ; offre = photo + bandeau couleur avec l'offre ; avis = citation d'un vrai avis ;
chiffre = un chiffre clé géant ; probleme = le problème barré puis la solution ; question = une question qui interpelle ; ugc = photo naturelle façon publication d'un client).

Réponds UNIQUEMENT en JSON :
{"creatives": [{"key": "c1", "axis": "preuve sociale | bénéfice | urgence | curiosité | offre | problème/solution", "concept": "l'idée en 1 phrase",
  "layout": "une des mises en page", "hook": "≤ 6 mots", "sub": "≤ 10 mots", "stat": "chiffre clé si layout chiffre, sinon vide", "problem": "le problème si layout probleme, sinon vide",
  "review": 0, "cta": "≤ 3 mots", "photo": 0, "use_ai_image": false, "scene": "description précise de la photo publicitaire à créer (si use_ai_image) : sujet, lieu, lumière, cadrage, sans texte"}],
 "carousel": {"slides": [{"title": "≤ 6 mots", "text": "≤ 14 mots"}], "photo": 0},
 "landing_hint": "en 1 phrase : la promesse que la page d'offre doit tenir pour être cohérente avec ces pubs"}
Le carrousel raconte une histoire en 5 vignettes : problème → conséquence → solution → preuve → offre et appel à l'action.
Mets use_ai_image à true pour 2 concepts au plus, quand aucune photo réelle ne convient ; sinon choisis la photo réelle la plus adaptée.
TXT;

        $data = $this->ask($prompt, 'AdsStudio.creatives');
        if (! $data || empty($data['creatives'])) {
            return null;
        }
        $reviewsList = array_values(collect($site->site_data['business']['reviews'] ?? [])->filter(fn ($r) => ($r['rating'] ?? 5) >= 4 && strlen($r['text'] ?? '') > 30)->take(3)->all());
        $out = [];
        foreach (array_slice((array) $data['creatives'], 0, 6) as $i => $c) {
            $layout = in_array($c['layout'] ?? '', self::LAYOUTS, true) ? $c['layout'] : 'impact';
            $rv = $layout === 'avis' ? ($reviewsList[(int) ($c['review'] ?? 0)] ?? null) : null;
            if ($layout === 'avis' && ! $rv) {
                $layout = 'impact'; // pas d'avis réel : on ne cite rien
            }
            $out[] = [
                'key' => 'c'.($i + 1), 'axis' => Str::limit((string) ($c['axis'] ?? ''), 40, ''), 'concept' => Str::limit((string) ($c['concept'] ?? ''), 200, ''),
                'layout' => $layout, 'hook' => Str::limit((string) ($c['hook'] ?? ''), 70, ''), 'sub' => Str::limit((string) ($c['sub'] ?? ''), 90, ''),
                'stat' => Str::limit((string) ($c['stat'] ?? ''), 12, ''), 'problem' => Str::limit((string) ($c['problem'] ?? ''), 70, ''),
                'review' => $rv ? ['text' => Str::limit($rv['text'], 220), 'author' => $rv['author'] ?? 'Client', 'rating' => $rv['rating'] ?? 5] : null,
                'cta' => Str::limit((string) ($c['cta'] ?? 'En savoir plus'), 24, ''),
                'image' => ! empty($c['use_ai_image']) ? null : ($photos[(int) ($c['photo'] ?? 0)] ?? ($photos[0] ?? null)),
                'scene' => Str::limit((string) ($c['scene'] ?? ''), 400, ''), 'use_ai_image' => ! empty($c['use_ai_image']) || ! $photos,
            ];
        }

        return [
            'creatives' => $out,
            'carousel' => ['slides' => array_slice(array_map(fn ($sl) => ['title' => Str::limit((string) ($sl['title'] ?? ''), 60, ''), 'text' => Str::limit((string) ($sl['text'] ?? ''), 120, '')], (array) ($data['carousel']['slides'] ?? [])), 0, 6),
                'image' => $photos[(int) ($data['carousel']['photo'] ?? 0)] ?? ($photos[0] ?? null)],
            'landing_hint' => Str::limit((string) ($data['landing_hint'] ?? ''), 200, ''),
        ];
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
{"title": "nom interne", "path": "url-courte-avec-tirets", "variants": [{"headline": "≤ 9 mots", "subheadline": "≤ 22 mots", "offer": "l'offre en une ligne", "bullets": ["3 bénéfices courts"], "cta": "≤ 4 mots"}, {...}],
 {$this->contentSchema()}}
{$this->contentRules()}
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

    private function contentSchema(): string
    {
        return self::CONTENT_SCHEMA;
    }

    private function contentRules(): string
    {
        return self::CONTENT_RULES;
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
