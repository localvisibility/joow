<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lit un site existant pour une refonte : page d'accueil + pages internes utiles (jusqu'à 9),
 * texte structuré, images exploitables, logo, coordonnées, horaires.
 * Lecture « simple » (HTML servi) : les sites entièrement rendus en JavaScript remontent peu de texte
 * et sont signalés comme « thin » pour être complétés par le questionnaire.
 */
class SiteImporter
{
    public const MAX_PAGES = 10;

    public const MAX_IMAGES = 14;

    private const UA = 'Mozilla/5.0 (compatible; JoowBot/1.0; +https://joow.fr) AppleWebKit/537.36 Chrome/124 Safari/537.36';

    private const PRIORITY = ['service', 'prestation', 'tarif', 'prix', 'propos', 'about', 'qui-sommes', 'equipe', 'équipe', 'realisation', 'réalisation', 'galerie', 'photo', 'contact', 'horaire', 'menu', 'carte', 'produit', 'boutique', 'chambre', 'soin', 'formation', 'avis', 'temoignage'];

    private const SKIP_IMG = ['logo', 'icon', 'sprite', 'pixel', 'badge', 'avatar', 'flag', 'arrow', 'loader', 'spinner', 'placeholder', 'blank', 'transparent', 'facebook', 'instagram', 'linkedin', 'google', 'tripadvisor', 'paiement', 'payment', 'visa', 'mastercard', 'captcha'];

    /** Vérifie rapidement qu'une URL répond avec du HTML lisible. Retourne l'URL finale ou null. */
    public function probe(string $url): ?string
    {
        $url = $this->normalizeUrl($url);
        if (! $url) {
            return null;
        }
        try {
            $res = Http::timeout(8)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html,*/*;q=0.8', 'Accept-Language' => 'fr-FR,fr;q=0.9'])->get($url);
            if (! $res->successful() || ! Str::contains(Str::lower((string) $res->header('Content-Type')), ['html', 'xml', 'text'])) {
                return null;
            }
            $html = (string) $res->body();
            if (strlen($html) < 200 || ! preg_match('/<(html|body|div|p)\b/i', $html)) {
                return null;
            }

            return (string) ($res->effectiveUri() ?: $url);
        } catch (\Throwable) {
            return null;
        }
    }

    public function crawl(string $url): array
    {
        $url = $this->normalizeUrl($url) ?? throw new \RuntimeException('Adresse invalide');
        $home = $this->fetch($url) ?? throw new \RuntimeException("Site illisible : $url");
        $base = (string) ($home['url'] ?? $url);
        $host = parse_url($base, PHP_URL_HOST);

        $pages = [$this->parsePage($home['html'], $base)];
        $links = $this->internalLinks($home['html'], $base, $host);

        if ($links) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($l) => $pool->as($l)->timeout(10)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html', 'Accept-Language' => 'fr-FR,fr;q=0.9'])->get($l),
                $links
            ));
            foreach ($links as $l) {
                $r = $responses[$l] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful() && Str::contains(Str::lower((string) $r->header('Content-Type')), 'html')) {
                    $p = $this->parsePage((string) $r->body(), $l);
                    if (mb_strlen($p['text']) > 120) {
                        $pages[] = $p;
                    }
                }
            }
        }

        // Fusion des informations transverses (coordonnées, images, logo)
        $allText = implode("\n", array_column($pages, 'text'))."\n".implode("\n", array_column($pages, 'raw_contacts'));
        $images = [];
        foreach ($pages as $p) {
            foreach ($p['images'] as $img) {
                if (! in_array($img, $images, true)) {
                    $images[] = $img;
                }
            }
        }
        $homeP = $pages[0];
        $name = $homeP['site_name'] ?: $this->nameFromTitle($homeP['doc_title'] ?: $homeP['title'], $host);
        $address = $this->findAddress($allText);
        $city = $address ? $this->cityOf($address) : null;

        $out = [
            'url'         => $base,
            'host'        => $host,
            'name'        => Str::limit(trim($name), 100, ''),
            'title'       => $homeP['title'],
            'description' => $homeP['description'],
            'logo'        => $homeP['logo'],
            'images'      => array_slice($images, 0, self::MAX_IMAGES),
            'phone'       => $this->findPhone($allText) ?: null,
            'email'       => $this->findEmail($allText) ?: null,
            'address'     => $address,
            'city'        => $city,
            'hours'       => $this->findHours($allText),
            'pages'       => array_map(fn ($p) => ['url' => $p['url'], 'title' => $p['title'], 'text' => Str::limit($p['text'], 6000, '')], $pages),
            'text_length' => array_sum(array_map(fn ($p) => mb_strlen($p['text']), $pages)),
            'crawled_at'  => now()->toIso8601String(),
        ];
        $out['thin'] = $out['text_length'] < 400;

        Log::info(sprintf('Refonte %s : %d page(s), %d images, %d caractères%s', $host, count($pages), count($out['images']), $out['text_length'], $out['thin'] ? ' (site pauvre en texte)' : ''));

        return $out;
    }

    // ───────────────────────────── extraction ─────────────────────────────

    private function fetch(string $url): ?array
    {
        try {
            $res = Http::timeout(12)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html,*/*;q=0.8', 'Accept-Language' => 'fr-FR,fr;q=0.9'])->get($url);
            if (! $res->successful()) {
                return null;
            }
            $html = (string) $res->body();

            return strlen($html) > 200 ? ['html' => $html, 'url' => (string) ($res->effectiveUri() ?: $url)] : null;
        } catch (\Throwable $e) {
            Log::warning("Refonte : lecture impossible de $url — ".$e->getMessage());

            return null;
        }
    }

    private function dom(string $html): \DOMDocument
    {
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $enc = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        if ($enc !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $enc);
        }
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return $doc;
    }

    private function parsePage(string $html, string $url): array
    {
        $doc = $this->dom($html);
        $xp = new \DOMXPath($doc);
        $meta = fn (string $q) => trim((string) ($xp->query($q)->item(0)?->getAttribute('content') ?? ''));

        $title = trim((string) ($xp->query('//title')->item(0)?->textContent ?? ''));
        $h1 = trim((string) ($xp->query('//h1')->item(0)?->textContent ?? ''));

        // Coordonnées : liens tel:/mailto: et lignes de l'en-tête, du pied de page et des blocs <address>
        // (lus avant la suppression de ces zones, qui ne servent pas au texte éditorial)
        $contacts = [];
        foreach ($xp->query('//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:")]') as $a) {
            $contacts[] = urldecode(preg_replace('/^(tel|mailto):/', '', $a->getAttribute('href')));
        }
        foreach ($xp->query('//header//*[self::p or self::li or self::span or self::a or self::td]|//footer//*[self::p or self::li or self::span or self::a or self::td]|//address//*|//address|//*[contains(@class,"contact") or contains(@class,"horaire") or contains(@class,"hours") or contains(@id,"contact")]//*[self::p or self::li or self::span or self::td]') as $n) {
            $t = preg_replace('/\s+/u', ' ', trim($n->textContent));
            if ($t !== '' && mb_strlen($t) < 160 && ! in_array($t, $contacts, true)) {
                $contacts[] = $t;
            }
        }

        // Images (avant suppression des blocs de navigation : le logo est souvent dans l'en-tête)
        $logo = null;
        $images = [];
        $og = $meta('//meta[@property="og:image"]');
        if ($og && $this->goodImage($og)) {
            $images[] = $this->abs($og, $url);
        }
        foreach ($xp->query('//img') as $img) {
            $src = $img->getAttribute('src') ?: ($img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src'));
            if (! $src && ($srcset = $img->getAttribute('srcset') ?: $img->getAttribute('data-srcset'))) {
                $src = trim(explode(' ', trim(explode(',', $srcset)[0]))[0]);
            }
            if (! $src || Str::startsWith($src, 'data:')) {
                continue;
            }
            $abs = $this->abs($src, $url);
            $hint = Str::lower($src.' '.$img->getAttribute('alt').' '.$img->getAttribute('class').' '.$img->getAttribute('id'));
            if (! $logo && Str::contains($hint, 'logo')) {
                $logo = $abs;

                continue;
            }
            $w = (int) $img->getAttribute('width');
            $h = (int) $img->getAttribute('height');
            if (($w && $w < 300) || ($h && $h < 200)) {
                continue;
            }
            if ($this->goodImage($abs) && ! in_array($abs, $images, true)) {
                $images[] = $abs;
            }
        }

        // Texte utile : on retire scripts, styles, navigation, pied de page, formulaires
        foreach ($xp->query('//script|//style|//noscript|//nav|//footer|//header|//form|//iframe|//svg|//*[@aria-hidden="true"]|//*[contains(@class,"cookie")]|//*[contains(@id,"cookie")]') as $n) {
            $n->parentNode?->removeChild($n);
        }
        $parts = [];
        $seen = [];
        foreach ($xp->query('//h1|//h2|//h3|//h4|//p|//li|//blockquote|//td|//dd') as $n) {
            $t = preg_replace('/\s+/u', ' ', trim($n->textContent));
            if (mb_strlen($t) < 3 || isset($seen[$t])) {
                continue;
            }
            // Un élément parent (li contenant des p) ne doit pas doubler ses enfants
            if (in_array($n->nodeName, ['li', 'td', 'dd', 'blockquote'], true) && $xp->query('.//p|.//h2|.//h3', $n)->length) {
                continue;
            }
            $seen[$t] = true;
            $prefix = match ($n->nodeName) { 'h1' => '# ', 'h2' => '## ', 'h3', 'h4' => '### ', 'li' => '- ', default => '' };
            $parts[] = $prefix.$t;
        }
        $text = Str::limit(implode("\n", $parts), 9000, '');

        return [
            'url'          => $url,
            'title'        => Str::limit($h1 ?: $title, 120, ''),
            'doc_title'    => $title,
            'site_name'    => $meta('//meta[@property="og:site_name"]') ?: null,
            'description'  => $meta('//meta[@name="description"]') ?: $meta('//meta[@property="og:description"]'),
            'logo'         => $logo,
            'images'       => $images,
            'text'         => $text,
            'raw_contacts' => implode("\n", $contacts),
        ];
    }

    private function internalLinks(string $html, string $base, ?string $host): array
    {
        $doc = $this->dom($html);
        $scored = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:'])) {
                continue;
            }
            $abs = $this->abs($href, $base);
            $u = parse_url($abs);
            if (! $u || ($u['host'] ?? null) !== $host) {
                continue;
            }
            $path = rtrim($u['path'] ?? '/', '/');
            if ($path === '' || preg_match('/\.(pdf|jpe?g|png|gif|webp|svg|zip|mp4|docx?|xlsx?)$/i', $path) || preg_match('#/(wp-|tag/|category/|feed|login|panier|cart|checkout|compte|account|mentions|legal|cgv|cgu|confidentialite|privacy|cookies|sitemap)#i', $path)) {
                continue;
            }
            $clean = ($u['scheme'] ?? 'https').'://'.$host.$path;
            if ($clean === rtrim($base, '/')) {
                continue;
            }
            $hay = Str::lower($path.' '.$a->textContent);
            $score = 1;
            foreach (self::PRIORITY as $i => $kw) {
                if (Str::contains($hay, $kw)) {
                    $score += 10 - min(9, intdiv($i, 3));
                }
            }
            // Les pages peu profondes d'abord
            $score -= substr_count($path, '/') * 2;
            $scored[$clean] = max($scored[$clean] ?? 0, $score);
        }
        arsort($scored);

        return array_slice(array_keys($scored), 0, self::MAX_PAGES - 1);
    }

    // ───────────────────────────── helpers ─────────────────────────────

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $u = parse_url($url);
        if (! $u || empty($u['host']) || ! Str::contains($u['host'], '.')) {
            return null;
        }
        // Pas d'adresses locales / privées
        if (preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|0\.)/', $u['host']) || Str::endsWith($u['host'], ['.local', '.internal', 'joow.fr'])) {
            return null;
        }

        return $url;
    }

    private function abs(string $src, string $base): string
    {
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        $b = parse_url($base);
        $scheme = $b['scheme'] ?? 'https';
        $host = $b['host'] ?? '';
        if (Str::startsWith($src, '//')) {
            return $scheme.':'.$src;
        }
        if (Str::startsWith($src, '/')) {
            return "$scheme://$host$src";
        }
        $dir = rtrim(dirname($b['path'] ?? '/'), '/');

        return "$scheme://$host$dir/".ltrim($src, './');
    }

    private function goodImage(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
        if (! preg_match('/\.(jpe?g|png|webp)(\?.*)?$/', $path) && ! Str::contains($path, ['/image', '/media', '/upload', '/photo'])) {
            return false;
        }
        foreach (self::SKIP_IMG as $bad) {
            if (Str::contains(Str::lower($url), $bad)) {
                return false;
            }
        }

        return true;
    }

    private function nameFromTitle(string $title, ?string $host): string
    {
        // « Accueil - Plomberie Dupont | Plombier à Lyon » → on garde le premier segment qui ressemble à un nom
        foreach (preg_split('/\s[|\-–—:·,]\s*|\s[|–—]\s*/u', $title) ?: [] as $seg) {
            $t = trim(preg_replace('/^(accueil|bienvenue( chez| sur| à)?|home|site officiel)\s*/iu', '', trim($seg)));
            if (mb_strlen($t) >= 3 && mb_strlen($t) <= 60 && ! preg_match('/^(accueil|bienvenue|home)$/iu', $t)) {
                return $t;
            }
        }

        return Str::title(str_replace(['-', '_'], ' ', explode('.', preg_replace('/^www\./', '', (string) $host))[0]));
    }

    private function findPhone(string $text): ?string
    {
        if (preg_match('/(?:\+33\s?[1-9]|0[1-9])(?:[\s.\-]?\d{2}){4}/', $text, $m)) {
            $digits = preg_replace('/\D/', '', $m[0]);
            if (str_starts_with($digits, '33') && strlen($digits) === 11) {
                $digits = '0'.substr($digits, 2);
            }

            return strlen($digits) === 10 ? trim(chunk_split($digits, 2, ' ')) : trim($m[0]);
        }

        return null;
    }

    private function findEmail(string $text): ?string
    {
        if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $m)) {
            $e = Str::lower($m[0]);

            return Str::contains($e, ['example', 'sentry', 'wixpress', 'wordpress']) ? null : $e;
        }

        return null;
    }

    private function findAddress(string $text): ?string
    {
        if (preg_match('/\b\d{1,4}(?:\s?(?:bis|ter))?,?\s+(?:rue|avenue|av\.|boulevard|bd|chemin|route|place|impasse|allée|allee|quai|cours|zone|za|zi|lieu-dit)\b[^\n,]{2,60},?\s*\d{5}\s+[A-Za-zÀ-ÿ\'\- ]{2,40}/iu', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }
        if (preg_match('/\b\d{5}\s+([A-Za-zÀ-ÿ\'\-]{2,}(?:\s[A-Za-zÀ-ÿ\'\-]{2,}){0,3})/u', $text, $m)) {
            return trim($m[0]);
        }

        return null;
    }

    private function cityOf(string $address): ?string
    {
        if (preg_match('/\d{5}\s+([A-Za-zÀ-ÿ\'\-]{2,}(?:\s[A-Za-zÀ-ÿ\'\-]{2,}){0,3})/u', $address, $m)) {
            return Str::title(Str::lower(trim($m[1])));
        }

        return null;
    }

    private function findHours(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n/', $text) as $line) {
            if (preg_match('/\b(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche|lun|mar|mer|jeu|ven|sam|dim)\b/iu', $line) && preg_match('/\d{1,2}\s?[h:]\s?\d{0,2}/u', $line) && mb_strlen($line) < 120) {
                $out[] = trim($line, "- \t");
            }
            if (count($out) >= 7) {
                break;
            }
        }

        return array_values(array_unique($out));
    }
}
