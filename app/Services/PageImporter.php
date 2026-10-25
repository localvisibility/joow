<?php

namespace App\Services;

use App\Services\Pages\PageSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Refonte : transforme les pages internes du site existant (texte extrait) en pages Joow structurées
 * (blocs texte, prestations, tarifs, galerie, FAQ, équipe…), réécrites dans le ton du métier.
 */
class PageImporter
{
    public const MAX_PAGES = 4;

    /** @param  array  $b  fiche normalisée (avec existing.pages) ; $content = contenu rédigé de l'accueil ; $photos = photos analysées (url, description) */
    public function build(array $b, array $content, array $photos, string $sectorLabel): array
    {
        if (! Gemini::enabled()) {
            return [];
        }
        $existing = array_slice((array) ($b['existing']['pages'] ?? []), 1); // la 1re est l'accueil, déjà traité
        $existing = array_values(array_filter($existing, fn ($p) => mb_strlen((string) ($p['text'] ?? '')) > 250));
        if (! $existing) {
            return [];
        }

        $pagesTxt = collect($existing)->take(8)->map(fn ($p, $i) => '['.($i + 1).'] '.($p['title'] ?? 'Page').' — '.($p['url'] ?? '')."\n".Str::limit(trim((string) $p['text']), 2500, '…'))->implode("\n\n");
        $photosTxt = collect($photos)->take(12)->map(fn ($ph, $i) => ($i + 1).'. '.($ph['description'] ?? $ph['category'] ?? 'photo'))->implode("\n") ?: '(aucune)';
        $homeSvc = collect($content['services'] ?? [])->pluck('name')->filter()->implode(', ');

        $prompt = <<<TXT
Tu es architecte de contenu web. Tu reconstruis les pages internes du site de « {$b['name']} » ({$sectorLabel}) à partir de leur contenu actuel, pour un nouveau site moderne.

PAGES ACTUELLES (texte extrait, parfois mal structuré) :
{$pagesTxt}

PHOTOS DISPONIBLES (numéro → description) :
{$photosTxt}

La page d'accueil existe déjà et contient : accroche, à propos, prestations ({$homeSvc}), parcours, FAQ, avis, contact. Ne la refais pas.

RÈGLES
- Garde TOUS les faits (prestations, prix, marques, chiffres, histoire, noms, zone, garanties) ; réécris en phrases courtes, concrètes, vouvoiement ; n'invente aucun fait.
- Crée au plus {$this->maxPages()} pages, seulement celles qui apportent du contenu propre (ex : détail des prestations, tarifs, réalisations/galerie, équipe/histoire, produits). Ignore : accueil, contact seul, mentions légales, panier, pages vides ou redondantes.
- Chaque page : 2 à 5 blocs, variés, dans l'ordre logique. Types et champs autorisés :
  text {title, body (paragraphes séparés par une ligne vide), photo (numéro ou 0), image_side ("left"|"right")}
  features {title, intro, items[{icon (classe Font Awesome 6 ex "fa-wrench"), title, desc}]} (3 à 6)
  steps {title, items[{title, desc}]} (3 à 5)
  gallery {title, photos[numéros]} (si des photos correspondent)
  faq {title, items[{q, a}]} (3 à 6)
  pricing {title, intro, items[{name, price, desc, features[]}]} (uniquement si des prix figurent dans le texte)
  team {title, items[{name, role, bio}]} (uniquement si des personnes sont nommées)
  testimonials {title, items[{text, author, role}]} (uniquement des citations présentes dans le texte)
  stats {title, items[{v, l}]} (chiffres réels du texte)
  cta {title, text, button}
- Termine chaque page par un bloc cta.

Réponds UNIQUEMENT avec ce JSON :
{"pages": [{"title": "titre court (≤ 4 mots)", "slug": "slug-court", "seo": "description 150 caractères", "hero": {"tag": "surtitre 2-4 mots", "title": "titre de page (≤ 9 mots)", "subtitle": "1 phrase", "photo": 0}, "blocks": [ … ]}]}
TXT;

        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.5, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 6000, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 70, 'PageImporter');
            $data = json_decode(Gemini::text($res), true);
            $pages = is_array($data['pages'] ?? null) ? $data['pages'] : [];
        } catch (\Throwable $e) {
            Log::warning('PageImporter : '.$e->getMessage());

            return [];
        }

        $url = fn ($n) => is_numeric($n) && (int) $n >= 1 ? ($photos[(int) $n - 1]['url'] ?? null) : null;
        $allPhotos = array_values(array_filter(array_map(fn ($ph) => $ph['url'] ?? null, $photos)));
        $out = [];
        $log = [];
        foreach (array_slice($pages, 0, self::MAX_PAGES) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $hero = is_array($p['hero'] ?? null) ? $p['hero'] : [];
            $hero['image'] = $url($hero['photo'] ?? 0);
            $blocks = [];
            $raw = (array) ($p['blocks'] ?? ($p['sections'] ?? []));
            foreach ($raw as $bl) {
                if (! is_array($bl)) {
                    continue;
                }
                $type = (string) ($bl['type'] ?? '');
                if ($type === 'text') {
                    $bl['image'] = $url($bl['photo'] ?? 0);
                }
                if ($type === 'gallery') {
                    $imgs = array_values(array_filter(array_map($url, (array) ($bl['photos'] ?? []))));
                    // Numéros absents ou hors liste : on garde la galerie avec les photos disponibles
                    $bl['images'] = $imgs ?: array_slice($allPhotos, 0, 6);
                    if (! $bl['images']) {
                        continue;
                    }
                }
                $blocks[] = $bl;
            }
            $page = ['title' => $p['title'] ?? 'Page', 'slug' => $p['slug'] ?? null, 'seo' => $p['seo'] ?? '', 'hero' => $hero, 'blocks' => $blocks];
            $norm = PageSchema::normalizePages([$page])[0] ?? null;
            $log[] = ($p['title'] ?? '?').' : '.count($raw).' bloc(s) reçus ('.implode(',', array_map(fn ($x) => is_array($x) ? ($x['type'] ?? '?') : '?', $raw)).') → '.count($norm['blocks'] ?? []).' retenus';
            // Jamais de page vide : repli sur le texte extrait de la page d'origine
            if (empty($norm['blocks'])) {
                $src = collect($existing)->first(fn ($e) => Str::contains(Str::lower((string) ($e['title'] ?? '')), Str::lower(Str::limit((string) ($p['title'] ?? ''), 12, '')))) ?? ($existing[count($out)] ?? null);
                $body = trim(preg_replace('/^#+\s*/m', '', (string) ($src['text'] ?? '')));
                $page['blocks'] = array_values(array_filter([
                    $body !== '' ? ['type' => 'text', 'title' => $p['hero']['title'] ?? ($p['title'] ?? ''), 'body' => Str::limit($body, 2500, '…'), 'image' => $allPhotos[0] ?? null] : null,
                    ['type' => 'cta', 'title' => 'Parlons de votre projet', 'text' => 'Un premier échange gratuit, sans engagement.', 'button' => ''],
                ]));
            }
            $out[] = $page;
        }
        Log::info('PageImporter '.$b['name'].' : '.implode(' | ', $log));

        return PageSchema::normalizePages($out);
    }

    private function maxPages(): int
    {
        return self::MAX_PAGES;
    }
}
