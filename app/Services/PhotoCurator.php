<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Curateur de photos : fait « regarder » les photos de la fiche Google par l'IA (vision) pour
 * les décrire, les noter et leur attribuer un rôle dans le site (fond du hero, portrait « à propos »,
 * illustration des services, galerie). Les descriptions nourrissent ensuite le rédacteur pour que
 * les textes parlent de ce que l'on voit réellement.
 *
 * Résultat : [
 *   'photos' => [ ['url','category','quality','landscape','description','subject'] … ],
 *   'images' => ['hero' => url|null, 'about' => url|null, 'services' => url|null, 'gallery' => [url…]],
 *   'notes'  => "texte prêt pour le prompt",
 * ]
 */
class PhotoCurator
{
    private const CATEGORIES = ['exterieur', 'interieur', 'plat', 'produit', 'equipe', 'travail', 'detail', 'autre'];

    public function curate(array $photos, string $sectorLabel, string $businessName): array
    {
        $photos = array_values(array_filter($photos, fn ($u) => is_string($u) && str_starts_with($u, 'http')));
        $photos = array_slice($photos, 0, 10);
        if (! $photos || ! Gemini::enabled()) {
            return $this->fallback($photos);
        }

        // Téléchargement en basse définition (analyse), limité en taille et en temps
        $parts = [];
        $kept = [];
        foreach ($photos as $i => $url) {
            $bytes = $this->download($url);
            if ($bytes === null) {
                continue;
            }
            $kept[] = $url;
            $parts[] = ['text' => 'Photo #'.count($kept)];
            $parts[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($bytes)]];
        }
        if (! $kept) {
            return $this->fallback($photos);
        }

        $n = count($kept);
        $cats = implode('|', self::CATEGORIES);
        $parts[] = ['text' => <<<TXT
Tu es directeur artistique pour le site web de « {$businessName} » ({$sectorLabel}). Analyse les {$n} photos ci-dessus (issues de sa fiche Google).
Réponds UNIQUEMENT en JSON : {"photos":[{"i":1,"category":"{$cats}","quality":1-5,"landscape":true|false,"people":true|false,"text_overlay":true|false,"description":"ce que l'on voit, en français, 6 à 14 mots, concret (lieu, lumière, matière, ambiance)","subject":"mot-clé court du sujet principal"}, …]}
Règles de notation : quality 5 = photo pro nette, bien exposée, cadrée large, exploitable en plein écran ; 1 = floue, sombre, logo/texte, capture d'écran, photo de menu/affiche, selfie. Pénalise fortement les photos avec du texte incrusté, les logos, les documents, les photos verticales serrées. « exterieur » = façade/devanture/terrasse ; « interieur » = salle/cabinet/atelier/boutique ; « plat » = nourriture/boisson ; « produit » = objet vendu ; « equipe » = personnes qui travaillent ; « travail » = geste métier/chantier/soin ; « detail » = gros plan matière/déco.
TXT];

        $res = Gemini::generate([
            'contents' => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 2048, 'thinkingConfig' => ['thinkingBudget' => 0]],
        ], 60, 'PhotoCurator');
        $data = json_decode(Gemini::text($res), true);
        $rows = is_array($data['photos'] ?? null) ? $data['photos'] : [];
        if (! $rows) {
            Log::info('PhotoCurator : analyse indisponible, repli sur l\'ordre Google');

            return $this->fallback($kept);
        }

        $meta = [];
        foreach ($rows as $r) {
            $i = (int) ($r['i'] ?? 0) - 1;
            if (! isset($kept[$i])) {
                continue;
            }
            $meta[$i] = [
                'url'          => $kept[$i],
                'category'     => in_array($r['category'] ?? '', self::CATEGORIES, true) ? $r['category'] : 'autre',
                'quality'      => max(1, min(5, (int) ($r['quality'] ?? 3))),
                'landscape'    => (bool) ($r['landscape'] ?? true),
                'people'       => (bool) ($r['people'] ?? false),
                'text_overlay' => (bool) ($r['text_overlay'] ?? false),
                'description'  => trim((string) ($r['description'] ?? '')),
                'subject'      => trim((string) ($r['subject'] ?? '')),
            ];
        }
        foreach ($kept as $i => $url) {
            $meta[$i] ??= ['url' => $url, 'category' => 'autre', 'quality' => 3, 'landscape' => true, 'people' => false, 'text_overlay' => false, 'description' => '', 'subject' => ''];
        }
        ksort($meta);
        $meta = array_values($meta);

        return ['photos' => $meta, 'images' => $this->assign($meta), 'notes' => $this->notes($meta)];
    }

    /** Attribution des rôles : hero (large, ambiance, sans texte), about (équipe/intérieur), services (travail/produit/plat), galerie (le reste, qualité ≥ 3). */
    private function assign(array $meta): array
    {
        $usable = array_values(array_filter($meta, fn ($p) => ! $p['text_overlay'] && $p['quality'] >= 2));
        $used = [];
        $pick = function (array $prefCats, bool $needLandscape = false, int $minQ = 3) use (&$usable, &$used) {
            $best = null;
            $bestScore = -1;
            foreach ($usable as $p) {
                if (in_array($p['url'], $used, true) || $p['quality'] < $minQ) {
                    continue;
                }
                if ($needLandscape && ! $p['landscape']) {
                    continue;
                }
                $rank = array_search($p['category'], $prefCats, true);
                $score = ($rank === false ? 0 : (count($prefCats) - $rank) * 10) + $p['quality'];
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $p;
                }
            }
            if ($best) {
                $used[] = $best['url'];
            }

            return $best['url'] ?? null;
        };

        $hero = $pick(['exterieur', 'interieur', 'plat', 'travail', 'detail', 'produit'], true, 4)
            ?? $pick(['exterieur', 'interieur', 'plat', 'travail', 'detail', 'produit', 'equipe'], true, 3)
            ?? $pick(['exterieur', 'interieur', 'plat', 'travail', 'detail', 'produit', 'equipe', 'autre'], false, 2);
        $about = $pick(['equipe', 'interieur', 'travail', 'exterieur', 'detail'], false, 3)
            ?? $pick(['equipe', 'interieur', 'travail', 'exterieur', 'detail', 'plat', 'produit', 'autre'], false, 2);
        $services = $pick(['travail', 'plat', 'produit', 'detail', 'interieur'], false, 3)
            ?? $pick(['travail', 'plat', 'produit', 'detail', 'interieur', 'exterieur', 'autre'], false, 2);

        $gallery = [];
        foreach ($usable as $p) {
            if (! in_array($p['url'], $used, true) && $p['quality'] >= 3) {
                $gallery[] = $p['url'];
            }
        }
        // Galerie : si trop courte, réutiliser les meilleures photos déjà placées (hors hero)
        if (count($gallery) < 3) {
            foreach ([$services, $about] as $u) {
                if ($u && ! in_array($u, $gallery, true)) {
                    $gallery[] = $u;
                }
            }
        }

        return ['hero' => $hero, 'about' => $about, 'services' => $services, 'gallery' => array_slice($gallery, 0, 8)];
    }

    /** Résumé lisible pour le rédacteur IA. */
    private function notes(array $meta): string
    {
        $lines = [];
        foreach ($meta as $i => $p) {
            if ($p['description'] === '') {
                continue;
            }
            $lines[] = sprintf('- Photo %d (%s, qualité %d/5) : %s', $i + 1, $p['category'], $p['quality'], $p['description']);
        }

        return implode("\n", $lines);
    }

    private function fallback(array $photos): array
    {
        return [
            'photos' => array_map(fn ($u) => ['url' => $u, 'category' => 'autre', 'quality' => 3, 'landscape' => true, 'people' => false, 'text_overlay' => false, 'description' => '', 'subject' => ''], $photos),
            'images' => ['hero' => $photos[0] ?? null, 'about' => $photos[1] ?? null, 'services' => $photos[2] ?? null, 'gallery' => array_slice($photos, 3, 6)],
            'notes'  => '',
        ];
    }

    /** Télécharge une version réduite (≈ 800 px) : les URL Places acceptent maxWidthPx, Unsplash w=. */
    private function download(string $url): ?string
    {
        $small = preg_replace('/maxWidthPx=\d+/', 'maxWidthPx=800', $url);
        $small = preg_replace('/([?&])w=\d+/', '$1w=800', $small);
        try {
            $res = Http::timeout(12)->withOptions(['allow_redirects' => true])->get($small);
            if (! $res->successful() || strlen($res->body()) < 2_000 || strlen($res->body()) > 4_000_000) {
                return null;
            }
            $type = strtolower((string) $res->header('Content-Type'));
            if ($type && ! str_contains($type, 'image/')) {
                return null;
            }

            return $res->body();
        } catch (\Throwable $e) {
            Log::info('PhotoCurator : téléchargement impossible '.$e->getMessage());

            return null;
        }
    }
}
