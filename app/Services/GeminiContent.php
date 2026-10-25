<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Génère le contenu éditorial d'un site à partir de la fiche établissement, du brief métier
 * (config/sectors.php) et de la lecture des photos (PhotoCurator), pour des textes qui parlent
 * de ce que le visiteur voit réellement.
 */
class GeminiContent
{
    public function generate(array $business, string $sector, string $sectorLabel, array $photoNotes = [], string $brief = ''): array
    {
        $fallback = $this->fallback($business, $sectorLabel);
        if (! Gemini::enabled()) {
            return $fallback;
        }

        $reviews = collect($business['reviews'] ?? [])->filter(fn ($r) => strlen((string) ($r['text'] ?? '')) > 20)->take(6)
            ->map(fn ($r) => '- ('.($r['rating'] ?? '?').'/5) '.Str::limit(trim((string) $r['text']), 320))->implode("\n") ?: '- (aucun avis textuel)';
        $hours = implode(' ; ', (array) ($business['opening_hours'] ?? [])) ?: 'non renseignés';
        $types = implode(', ', array_slice((array) ($business['types'] ?? []), 0, 8)) ?: '—';
        $primary = $business['primary_type'] ?? ($business['types'][0] ?? $sectorLabel);
        $city = $business['city'] ?? 'France';
        $notes = $photoNotes['notes'] ?? '';
        $photoBlock = $notes !== ''
            ? "PHOTOS DU SITE (ce que le visiteur verra, décrites par analyse d'image) :\n{$notes}\nRôles : photo de fond du haut de page = ".($photoNotes['hero_desc'] ?? 'voir ci-dessus').' ; photo « à propos » = '.($photoNotes['about_desc'] ?? 'voir ci-dessus').' ; photo des services = '.($photoNotes['services_desc'] ?? 'voir ci-dessus').'.'
            : "PHOTOS : aucune photo exploitable sur la fiche ; des visuels d'illustration du métier seront utilisés. N'évoque donc aucun détail visuel précis (couleur des murs, plat, véhicule…).";
        $briefBlock = $brief !== '' ? "BRIEF MÉTIER (à respecter) :\n{$brief}\n" : '';
        $brand = $business['name'];
        $sourceBlock = $this->sourceBlock($business, $sectorLabel, $primary, $types, $hours, $reviews);

        $prompt = <<<TXT
Tu es directeur de création et rédacteur web senior. Tu écris le site vitrine de « {$brand} », {$sectorLabel} à {$city}, en français.
Objectif : un site qui donne envie, crédible, spécifique à CET établissement (pas un texte générique réutilisable pour un concurrent), orienté vers l'action (réserver, appeler, demander un devis).

{$sourceBlock}

{$photoBlock}

{$briefBlock}
RÈGLES D'ÉCRITURE
- Cohérence texte/photo : les titres et paragraphes doivent évoquer ce que montrent les photos (lieu, ambiance, gestes, produits) quand des photos sont décrites. Ne décris rien qui n'apparaît ni dans les photos ni dans les avis.
- Interdits : « Bienvenue », « qualité », « professionnel(le) », « votre partenaire », « n'hésitez pas », « au cœur de » répété, superlatifs vides, le nom seul en titre, les faux chiffres (années d'existence, nombre de clients) non appuyés par la fiche ou les avis. Les stats doivent provenir de données réelles (note Google, nombre d'avis, horaires, ville) ou être qualitatives (« Fait maison », « Sur rendez-vous »).
- Prix : réalistes pour le métier et la ville, au format « dès 29 € », « 45 € », « Sur devis » ; jamais de prix pour les professions réglementées (santé, avocat, notaire) : « Sur demande » ou « Tarif conventionné » seulement si les avis l'indiquent.
- Longueurs : hero_title ≤ 7 mots, hero_subtitle ≤ 20 mots, about 2 paragraphes de 2-3 phrases, services 6 (nom ≤ 5 mots, desc 1 phrase de bénéfice), faq 5 (réponses 2-3 phrases utiles), process 3 étapes.
- Ton : celui du brief métier ; phrases courtes ; tutoiement interdit, vouvoiement.

Réponds UNIQUEMENT avec ce JSON (toutes les clés, valeurs en français) :
{
  "hero_title": "accroche émotionnelle et spécifique",
  "hero_subtitle": "ce qu'on y trouve, pour qui, où",
  "tagline": "surtitre 2-5 mots (spécialité ou promesse)",
  "about_tag": "surtitre de la section à propos (2-4 mots)",
  "about_title": "titre à propos (≤ 8 mots), lié à la photo « à propos »",
  "about_p1": "présentation concrète : qui, quoi, depuis quand si les informations le disent, ce qui fait la différence",
  "about_p2": "méthode / engagement / expérience client, en lien avec les avis ou les informations fournies",
  "badges": ["4 points forts de 2-3 mots, issus des avis, des informations fournies ou du métier"],
  "stats": [{"v": "valeur courte réelle ou qualitative", "l": "libellé 2-3 mots"}, {"v": "...", "l": "..."}, {"v": "...", "l": "..."}],
  "services_tag": "surtitre services (2-3 mots)",
  "services_title": "titre services (≤ 7 mots)",
  "services_intro": "1 phrase d'introduction",
  "services": [{"name": "...", "desc": "...", "price": "...", "photo": 0}],
  "process_title": "titre du parcours (≤ 7 mots)",
  "process": [{"title": "étape (3-5 mots, commence par Vous ou On)", "desc": "1 phrase rassurante"}],
  "faq_title": "titre FAQ (≤ 6 mots)",
  "faq": [{"q": "question réelle que se posent les clients", "a": "réponse claire"}],
  "reviews_title": "titre de la section avis (≤ 7 mots)",
  "gallery_title": "titre de la galerie (≤ 5 mots), lié aux photos",
  "cta_band_title": "titre de la bande d'appel à l'action (≤ 8 mots, incitatif)",
  "cta_text": "phrase d'appel à l'action chaleureuse",
  "contact_title": "titre de la section contact (≤ 6 mots)"
}
« photo » dans services = numéro de la photo (1 à N) qui illustre le mieux ce service, ou 0 si aucune ne convient.
TXT;

        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.65, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 4096, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 75, 'GeminiContent');
            $text = Gemini::text($res);
            $data = $text ? json_decode($text, true) : null;
            if (! is_array($data)) {
                return $fallback;
            }

            return array_merge($fallback, array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []));
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /** Bloc « matière première » du prompt, selon l'origine des informations. */
    private function sourceBlock(array $b, string $sectorLabel, string $primary, string $types, string $hours, string $reviews): string
    {
        $owner = (array) ($b['owner'] ?? []);
        $ownerLines = array_filter([
            ! empty($owner['description']) ? '- Présentation par le propriétaire : '.Str::limit($owner['description'], 600) : null,
            ! empty($owner['services']) ? '- Prestations déclarées (à reprendre telles quelles, complète jusqu\'à 6 si pertinent) : '.implode(' ; ', array_slice($owner['services'], 0, 10)) : null,
            ! empty($owner['strengths']) ? '- Points forts déclarés : '.implode(' ; ', array_slice($owner['strengths'], 0, 8)) : null,
            ! empty($owner['since']) ? '- Activité depuis : '.$owner['since'] : null,
            ! empty($owner['zone']) ? '- Zone d\'intervention : '.$owner['zone'] : null,
            ! empty($owner['hours']) ? '- Horaires indiqués : '.$owner['hours'] : null,
        ]);
        $ownerBlock = $ownerLines ? "\nINFORMATIONS DONNÉES PAR LE PROPRIÉTAIRE (source de vérité, prioritaire) :\n".implode("\n", $ownerLines) : '';
        $noReviews = "\n- Aucun avis ni note Google : n'invente AUCUN avis, aucune note, aucun nombre d'avis ; « stats » = faits qualitatifs ou issus des informations ci-dessus (ex : « Devis gratuit », « 7j/7 », année de création).";

        if (($b['source'] ?? 'google') === 'questionnaire') {
            return "ENTREPRISE (décrite par son propriétaire, pas de fiche Google)\n- Nom : {$b['name']}\n- Métier : ".($b['primary_type'] ?: $sectorLabel)."\n- Adresse / ville : {$b['address']}\n- Horaires : {$hours}{$noReviews}{$ownerBlock}";
        }

        if (($b['source'] ?? 'google') === 'refonte') {
            $pages = collect($b['existing']['pages'] ?? [])->map(fn ($p) => '### '.($p['title'] ?? $p['url'] ?? 'Page')."\n".Str::limit(trim((string) ($p['text'] ?? '')), 1800))->take(8)->implode("\n\n");
            $gl = ! empty($b['rating']) ? "- Note Google : {$b['rating']}/5 ({$b['reviews_count']} avis)\n- Avis clients (réutilise les mots des clients, jamais de citation inventée) :\n{$reviews}" : rtrim($noReviews, '.').'.';

            return "REFONTE DU SITE EXISTANT ({$b['website']})\n- Nom : {$b['name']}\n- Métier : ".($b['primary_type'] ?: $sectorLabel)." · types : {$types}\n- Adresse : {$b['address']}\n- Horaires : {$hours}\n{$gl}{$ownerBlock}\n\nCONTENU ACTUEL DU SITE (matière première : conserve tous les faits — prestations, prix, noms, chiffres, histoire, zone — et réécris-les en mieux ; n'ajoute aucun fait absent) :\n{$pages}";
        }

        $existing = '';
        if (! empty($b['existing']['pages'])) {
            $pages = collect($b['existing']['pages'])->map(fn ($p) => '### '.($p['title'] ?? $p['url'] ?? 'Page')."\n".Str::limit(trim((string) ($p['text'] ?? '')), 1500))->take(6)->implode("\n\n");
            $existing = "\n\nSITE ACTUEL DE L'ÉTABLISSEMENT ({$b['website']}) — matière première complémentaire : reprends les faits (prestations, prix, histoire, équipe, zone, garanties, marques), jamais les formulations creuses ; en cas de contradiction, la fiche Google et les avis priment pour les coordonnées et horaires :\n{$pages}";
        }

        return "FICHE GOOGLE\n- Nom : {$b['name']}\n- Type principal : {$primary} · types : {$types}\n- Adresse : {$b['address']}\n- Note : {$b['rating']}/5 ({$b['reviews_count']} avis)\n- Horaires : {$hours}\n- Avis clients (matière première : réutilise les mots des clients, les détails concrets, jamais de citation inventée) :\n{$reviews}{$ownerBlock}{$existing}";
    }

    private function fallback(array $business, string $sectorLabel): array
    {
        $city = $business['city'] ?? 'votre ville';

        return [
            'hero_title'    => $business['name'],
            'hero_subtitle' => "{$sectorLabel} à {$city}",
            'tagline'       => "{$sectorLabel} · {$city}",
            'about_p1'      => "{$business['name']} vous accueille à {$city} avec attention et proximité.",
            'about_p2'      => 'Un interlocuteur unique, des tarifs clairs et un suivi attentif à chaque étape.',
            'badges'        => ['Devis gratuit', 'Réponse rapide', 'Travail soigné', 'Proximité'],
            'stats'         => [],
            'services'      => [],
            'faq'           => [],
            'cta_text'      => 'Contactez-nous pour un premier échange gratuit.',
        ];
    }
}
