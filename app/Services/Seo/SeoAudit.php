<?php

namespace App\Services\Seo;

use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Support\Str;

/**
 * Audit SEO d'un site Joow : lit le HTML réellement publié de chaque page et vérifie
 * titre, description, titres H1/H2, volume de texte, mot-clé, images, liens, puis le site
 * (mise en ligne, domaine, téléphone cliquable, pages de services). Note sur 100 + conseils.
 */
class SeoAudit
{
    public const TITLE = [30, 62];

    public const DESC = [70, 158];

    public function run(Site $site): array
    {
        $renderer = app(SiteRenderer::class);
        $all = $renderer->renderAll($site);
        $labels = ['' => 'Accueil'] + collect($renderer->pages($site))->mapWithKeys(fn ($p) => [$p['slug'] => $p['title']])->all();
        $seo = (array) ($site->site_data['seo'] ?? []);
        $city = (string) ($site->site_data['business']['city'] ?? $site->city ?? '');
        $base = SeoApply::baseUrl($site);

        $parsed = [];
        foreach ($all as $slug => $html) {
            $parsed[$slug] = $this->parse($html);
        }
        $titles = array_count_values(array_map(fn ($p) => Str::lower($p['title']), $parsed));
        $descs = array_count_values(array_filter(array_map(fn ($p) => Str::lower($p['description']), $parsed)));

        $pages = [];
        foreach ($parsed as $slug => $p) {
            $key = SeoApply::key($slug ?: null);
            $kw = trim((string) ($seo['keywords'][$key] ?? ''));
            $pages[] = [
                'key' => $key,
                'slug' => $slug,
                'label' => $labels[$slug] ?? $slug,
                'url' => $base.'/'.($slug ? $slug.'/' : ''),
                'title' => $p['title'],
                'description' => $p['description'],
                'h1' => $p['h1'],
                'h2' => array_slice($p['h2'], 0, 12),
                'words' => $p['words'],
                'images' => $p['images'],
                'keyword' => $kw,
                'custom' => (array) ($seo['meta'][$key] ?? []),
                'checks' => $this->pageChecks($p, $slug, $kw, $city, $titles, $descs, count($all), $labels[$slug] ?? $slug),
            ];
        }
        $siteChecks = $this->siteChecks($site, $parsed[''] ?? null, count($all));
        $all = array_merge($siteChecks, ...array_column($pages, 'checks'));

        return [
            'score' => $this->score($all),
            'counts' => ['error' => count(array_filter($all, fn ($c) => $c['status'] === 'error')), 'warn' => count(array_filter($all, fn ($c) => $c['status'] === 'warn')), 'ok' => count(array_filter($all, fn ($c) => $c['status'] === 'ok'))],
            'site' => $siteChecks,
            'pages' => $pages,
            'base' => $base,
            'at' => now()->toIso8601String(),
        ];
    }

    private function parse(string $html): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $txt = fn ($n) => trim(preg_replace('/\s+/u', ' ', $n->textContent));

        $title = ($n = $xp->query('//title')->item(0)) ? $txt($n) : '';
        $desc = ($n = $xp->query('//meta[@name="description"]/@content')->item(0)) ? trim($n->nodeValue) : '';
        $h1 = array_values(array_filter(array_map($txt, iterator_to_array($xp->query('//h1')))));
        $h2 = array_values(array_filter(array_map($txt, iterator_to_array($xp->query('//h2')))));

        $images = [];
        foreach ($xp->query('//img[@src]') as $img) {
            $src = $img->getAttribute('src');
            if (str_starts_with($src, 'data:') || isset($images[$src])) {
                continue;
            }
            $images[$src] = ['src' => $src, 'alt' => trim($img->getAttribute('alt'))];
        }
        $links = 0;
        $tel = false;
        foreach ($xp->query('//a[@href]') as $a) {
            $h = $a->getAttribute('href');
            $tel = $tel || str_starts_with($h, 'tel:');
            if (preg_match('#^/(?!/)[^\#]#', $h) || preg_match('#^\.\./#', $h)) {
                $links++;
            }
        }
        foreach ($xp->query('//script|//style|//noscript|//nav|//footer|//*[@id="joow-bar"]') as $n) {
            $n->parentNode?->removeChild($n);
        }
        $body = ($b = $xp->query('//body')->item(0)) ? $txt($b) : '';

        return ['title' => $title, 'description' => $desc, 'h1' => $h1, 'h2' => $h2, 'images' => array_values($images), 'links' => $links, 'tel' => $tel,
            'words' => str_word_count(Str::ascii($body)), 'text' => Str::lower(Str::ascii(Str::limit($body, 20000, ''))), 'first' => Str::lower(Str::ascii(Str::words($body, 120, '')))];
    }

    private function has(string $hay, string $needle): bool
    {
        $n = Str::lower(Str::ascii($needle));

        return $n !== '' && str_contains(Str::lower(Str::ascii($hay)), $n);
    }

    private function check(string $id, string $status, string $label, string $advice = '', ?string $fix = null, ?string $ask = null, int $weight = 2, ?string $page = null): array
    {
        return compact('id', 'status', 'label', 'advice', 'fix', 'ask', 'weight', 'page');
    }

    private function pageChecks(array $p, string $slug, string $kw, string $city, array $titles, array $descs, int $nPages, string $label): array
    {
        $c = [];
        $on = $slug ?: 'index';
        $where = $slug ? "de la page « $label »" : "de la page d'accueil";
        $tl = mb_strlen($p['title']);
        $c[] = match (true) {
            $tl === 0 => $this->check('title', 'error', 'Titre de la page absent', 'Le titre est la ligne bleue cliquable dans Google : c\'est l\'élément le plus important.', 'meta', null, 3, $on),
            ($titles[Str::lower($p['title'])] ?? 0) > 1 => $this->check('title', 'error', 'Titre identique à une autre page', 'Chaque page doit avoir son propre titre, sinon Google ne sait pas laquelle afficher.', 'meta', null, 3, $on),
            $tl < self::TITLE[0] => $this->check('title', 'warn', "Titre trop court ($tl caractères)", 'Visez 50 à 60 caractères : activité + ville + nom.', 'meta', null, 3, $on),
            $tl > self::TITLE[1] => $this->check('title', 'warn', "Titre trop long ($tl caractères)", 'Au-delà de ~60 caractères, Google le coupe avec « … ».', 'meta', null, 3, $on),
            default => $this->check('title', 'ok', "Titre de bonne longueur ($tl caractères)", '', 'meta', null, 3, $on),
        };
        $dl = mb_strlen($p['description']);
        $c[] = match (true) {
            $dl === 0 => $this->check('description', 'error', 'Description absente', 'Le texte gris sous le titre dans Google : il donne envie de cliquer.', 'meta', null, 2, $on),
            ($descs[Str::lower($p['description'])] ?? 0) > 1 => $this->check('description', 'warn', 'Description identique à une autre page', 'Rédigez une description propre à chaque page.', 'meta', null, 2, $on),
            $dl < self::DESC[0] => $this->check('description', 'warn', "Description trop courte ($dl caractères)", 'Visez 120 à 155 caractères avec un bénéfice et un appel à l\'action.', 'meta', null, 2, $on),
            $dl > self::DESC[1] => $this->check('description', 'warn', "Description trop longue ($dl caractères)", 'Google coupe vers 155 caractères.', 'meta', null, 2, $on),
            default => $this->check('description', 'ok', "Description de bonne longueur ($dl caractères)", '', 'meta', null, 2, $on),
        };
        if ($kw === '') {
            $c[] = $this->check('keyword', 'warn', 'Aucune expression clé choisie', 'Choisissez la recherche principale que cette page doit capter (ex. « plombier Chartres »). L\'IA peut vous en proposer.', 'keywords', null, 2, $on);
        } else {
            $c[] = $this->has($p['title'], $kw)
                ? $this->check('kw_title', 'ok', "« $kw » est dans le titre", '', null, null, 3, $on)
                : $this->check('kw_title', 'warn', "« $kw » n'apparaît pas dans le titre", 'Placez l\'expression clé au début du titre.', 'meta', null, 3, $on);
            $c[] = $this->has(implode(' ', $p['h1']), $kw)
                ? $this->check('kw_h1', 'ok', "« $kw » est dans le grand titre (H1)", '', null, null, 2, $on)
                : $this->check('kw_h1', 'warn', "« $kw » n'est pas dans le grand titre (H1)", 'Le grand titre visible de la page doit reprendre l\'expression clé, naturellement.', null, "Reformule le grand titre $where pour qu'il contienne naturellement l'expression « $kw », en restant court et accrocheur.", 2, $on);
            $c[] = $this->has($p['first'], $kw)
                ? $this->check('kw_intro', 'ok', "« $kw » apparaît dès le début du texte", '', null, null, 1, $on)
                : $this->check('kw_intro', 'warn', "« $kw » absent du début du texte", 'Mentionnez l\'expression dans les premières lignes.', null, "Mentionne naturellement l'expression « $kw » dans le premier paragraphe $where, sans bourrage de mots-clés.", 1, $on);
        }
        $h1 = count($p['h1']);
        $c[] = match (true) {
            $h1 === 0 => $this->check('h1', 'error', 'Pas de grand titre (H1)', 'Chaque page doit avoir un grand titre principal.', null, "Ajoute un grand titre principal clair $where.", 2, $on),
            $h1 > 1 => $this->check('h1', 'warn', "$h1 grands titres (H1) sur la page", 'Gardez un seul H1 ; les autres titres doivent être des sous-titres.', null, null, 1, $on),
            default => $this->check('h1', 'ok', 'Un grand titre (H1) unique', '', null, null, 2, $on),
        };
        $c[] = count($p['h2']) >= 2
            ? $this->check('h2', 'ok', count($p['h2']).' sous-titres (H2) structurent la page', '', null, null, 1, $on)
            : $this->check('h2', 'warn', 'Page peu structurée (moins de 2 sous-titres)', 'Des sous-titres clairs aident Google et vos visiteurs à lire la page.', null, "Structure la page $where avec des sous-titres clairs par thème (prestations, zone d'intervention, questions fréquentes).", 1, $on);
        $min = $slug ? 250 : 350;
        $c[] = $p['words'] >= $min
            ? $this->check('words', 'ok', "{$p['words']} mots de contenu", '', null, null, 2, $on)
            : $this->check('words', 'warn', "Contenu un peu léger ({$p['words']} mots)", "Visez au moins $min mots utiles : prestations détaillées, zone d'intervention, questions fréquentes.", null, "Enrichis $where avec environ 200 mots utiles pour mes clients (prestations détaillées, zone d'intervention, questions fréquentes), sans inventer de prix, d'avis, de chiffres ni de certifications.", 2, $on);
        if ($city !== '' && ! $slug) {
            $c[] = $this->has($p['text'], $city)
                ? $this->check('city', 'ok', "La ville ($city) est mentionnée", '', null, null, 2, $on)
                : $this->check('city', 'warn', "La ville ($city) n'est pas mentionnée", 'Pour le référencement local, citez votre ville et votre zone d\'intervention.', null, "Mentionne naturellement la ville de $city et la zone d'intervention dans le texte de la page d'accueil.", 2, $on);
        }
        $missing = count(array_filter($p['images'], fn ($i) => $i['alt'] === ''));
        if ($p['images']) {
            $c[] = $missing
                ? $this->check('alts', 'warn', "$missing image(s) sans description (texte alternatif)", 'Google « lit » les images grâce à leur description : elle aide aussi Google Images.', 'alts', null, 1, $on)
                : $this->check('alts', 'ok', 'Toutes les images sont décrites', '', null, null, 1, $on);
        }
        if ($nPages > 1) {
            $c[] = $p['links'] >= 1
                ? $this->check('links', 'ok', 'Liens vers les autres pages du site', '', null, null, 1, $on)
                : $this->check('links', 'warn', 'Aucun lien vers vos autres pages', 'Reliez vos pages entre elles : Google les découvre et les comprend mieux.', null, "Ajoute dans le texte $where des liens vers mes autres pages utiles.", 1, $on);
        }

        return $c;
    }

    private function siteChecks(Site $site, ?array $home, int $nPages): array
    {
        $c = [];
        $online = in_array($site->status, ['paid', 'published'], true);
        $c[] = $online
            ? $this->check('online', 'ok', 'Site en ligne et indexable par Google', '', null, null, 3)
            : $this->check('online', 'error', 'Site pas encore en ligne', 'Un aperçu n\'est pas indexé par Google : mettez votre site en ligne pour apparaître dans les résultats.', 'publish', null, 3);
        $c[] = ($site->domain_ssl_active && $site->custom_domain)
            ? $this->check('domain', 'ok', "Nom de domaine propre ($site->custom_domain) en HTTPS", '', null, null, 2)
            : $this->check('domain', 'warn', 'Pas encore de nom de domaine propre', 'Une adresse .fr ou .com à votre nom inspire confiance et se référence mieux qu\'un sous-domaine.', 'domain', null, 2);
        $c[] = ($home['tel'] ?? false)
            ? $this->check('tel', 'ok', 'Téléphone cliquable sur mobile', '', null, null, 1)
            : $this->check('tel', 'warn', 'Téléphone non cliquable', 'Sur mobile, un numéro cliquable augmente fortement les appels.', null, 'Ajoute mon numéro de téléphone en lien cliquable (appel direct) en haut de la page d\'accueil.', 1);
        $c[] = $nPages >= 3
            ? $this->check('pages', 'ok', "$nPages pages sur le site", '', null, null, 2)
            : $this->check('pages', 'warn', 'Peu de pages ('.$nPages.')', 'Une page par prestation principale (ex. « Débouchage », « Dépannage chauffe-eau ») multiplie vos chances d\'apparaître sur ces recherches.', null, 'Crée une page dédiée à ma prestation principale, avec un texte détaillé, une FAQ et un appel à l\'action, sans inventer de prix ni d\'avis.', 2);
        $c[] = ($site->rating && $site->reviews_count)
            ? $this->check('reviews', 'ok', "Avis Google affichés ({$site->reviews_count} avis)", '', null, null, 1)
            : $this->check('reviews', 'warn', 'Pas d\'avis Google affichés', 'Créez ou complétez votre fiche Google Business Profile et demandez des avis à vos clients : c\'est le premier critère du référencement local.', null, null, 1);
        $c[] = $this->check('tech', 'ok', 'Plan du site, robots.txt, données structurées et adresse canonique générés automatiquement', '', null, null, 2);

        return $c;
    }

    private function score(array $checks): int
    {
        $total = 0;
        $got = 0;
        foreach ($checks as $c) {
            $total += $c['weight'];
            $got += $c['status'] === 'ok' ? $c['weight'] : ($c['status'] === 'warn' ? $c['weight'] * 0.35 : 0);
        }

        return $total ? (int) round($got / $total * 100) : 0;
    }
}
