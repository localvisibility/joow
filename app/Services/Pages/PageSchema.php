<?php

namespace App\Services\Pages;

use Illuminate\Support\Str;

/**
 * Schéma des pages additionnelles d'un site (site_data.pages).
 *
 * Une page = { slug, title, nav, seo, hero{tag,title,subtitle,image}, blocks[] }
 * Un bloc  = { type, ...champs propres au type } — voir BLOCKS.
 *
 * Utilisé par le rendu (Blade), le Studio (constructeur) et l'agent IA (opérations).
 */
class PageSchema
{
    public const MAX_PAGES = 12;
    public const MAX_BLOCKS = 20;
    public const RESERVED = ['index', 'api', 'media', 'assets', 'static', 'admin', 'joow', 'sitemap', 'robots'];

    /** Définition des blocs : libellé, description (pour l'IA) et champs. */
    public const BLOCKS = [
        'text'         => ['label' => 'Texte & image', 'desc' => 'Titre, paragraphes (séparés par une ligne vide) et image optionnelle à gauche ou à droite.', 'fields' => ['title', 'body', 'image', 'image_side']],
        'features'     => ['label' => 'Points forts / prestations', 'desc' => 'Titre, intro et 3 à 6 cartes {icon (Font Awesome), title, desc}.', 'fields' => ['title', 'intro', 'items']],
        'steps'        => ['label' => 'Étapes', 'desc' => 'Titre et 3 à 5 étapes numérotées {title, desc}.', 'fields' => ['title', 'items']],
        'gallery'      => ['label' => 'Galerie photos', 'desc' => 'Titre et liste d\'URL d\'images (utilise les photos du site si besoin).', 'fields' => ['title', 'images']],
        'faq'          => ['label' => 'Questions / réponses', 'desc' => 'Titre et questions {q, a}.', 'fields' => ['title', 'items']],
        'pricing'      => ['label' => 'Tarifs', 'desc' => 'Titre et offres {name, price, desc, features[]}.', 'fields' => ['title', 'intro', 'items']],
        'team'         => ['label' => 'Équipe', 'desc' => 'Titre et membres {name, role, bio, image}.', 'fields' => ['title', 'items']],
        'testimonials' => ['label' => 'Témoignages', 'desc' => 'Titre et citations {text, author, role}.', 'fields' => ['title', 'items']],
        'stats'        => ['label' => 'Chiffres clés', 'desc' => '3 ou 4 chiffres {v, l}.', 'fields' => ['title', 'items']],
        'cta'          => ['label' => 'Appel à l\'action', 'desc' => 'Bande colorée avec titre, texte et bouton vers la réservation / le contact.', 'fields' => ['title', 'text', 'button']],
        'contact'      => ['label' => 'Contact & horaires', 'desc' => 'Coordonnées, horaires et carte de l\'établissement (automatique).', 'fields' => ['title', 'text']],
        'video'        => ['label' => 'Vidéo', 'desc' => 'Titre et URL YouTube/Vimeo.', 'fields' => ['title', 'url']],
    ];

    /** Nettoie et complète une liste de pages. */
    public static function normalizePages(mixed $pages): array
    {
        if (! is_array($pages)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach (array_values($pages) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $title = trim((string) ($p['title'] ?? '')) ?: 'Nouvelle page';
            $slug = self::slug((string) ($p['slug'] ?? $title));
            if ($slug === '' || in_array($slug, self::RESERVED, true)) {
                $slug = 'page';
            }
            $base = $slug;
            $n = 2;
            while (isset($seen[$slug])) {
                $slug = $base.'-'.$n++;
            }
            $seen[$slug] = true;

            $hero = is_array($p['hero'] ?? null) ? $p['hero'] : [];
            $blocks = [];
            foreach (array_values(is_array($p['blocks'] ?? null) ? $p['blocks'] : []) as $bl) {
                if ($b = self::normalizeBlock($bl)) {
                    $blocks[] = $b;
                }
                if (count($blocks) >= self::MAX_BLOCKS) {
                    break;
                }
            }

            $out[] = [
                'slug'   => $slug,
                'title'  => Str::limit($title, 60, ''),
                'nav'    => array_key_exists('nav', $p) ? (bool) $p['nav'] : true,
                'seo'    => Str::limit(trim((string) ($p['seo'] ?? '')), 200, ''),
                'hero'   => [
                    'tag'      => Str::limit(trim((string) ($hero['tag'] ?? '')), 60, ''),
                    'title'    => Str::limit(trim((string) ($hero['title'] ?? $title)), 120, ''),
                    'subtitle' => Str::limit(trim((string) ($hero['subtitle'] ?? '')), 300, ''),
                    'image'    => self::url($hero['image'] ?? null),
                ],
                'blocks' => $blocks,
            ];
            if (count($out) >= self::MAX_PAGES) {
                break;
            }
        }

        return $out;
    }

    /** Nettoie un bloc selon son type. Renvoie null si le type est inconnu. */
    public static function normalizeBlock(mixed $b): ?array
    {
        if (! is_array($b) || ! isset(self::BLOCKS[$b['type'] ?? ''])) {
            return null;
        }
        $type = $b['type'];
        $s = fn ($k, $max = 200) => Str::limit(trim((string) ($b[$k] ?? '')), $max, '');
        $items = array_values(array_filter(is_array($b['items'] ?? null) ? $b['items'] : [], 'is_array'));
        $items = array_slice($items, 0, 12);
        $out = ['type' => $type, 'title' => $s('title', 120)];

        switch ($type) {
            case 'text':
                $out += ['body' => $s('body', 4000), 'image' => self::url($b['image'] ?? null), 'image_side' => ($b['image_side'] ?? 'right') === 'left' ? 'left' : 'right'];
                break;
            case 'features':
                $out += ['intro' => $s('intro', 400), 'items' => array_map(fn ($i) => ['icon' => self::icon($i['icon'] ?? ''), 'title' => Str::limit(trim((string) ($i['title'] ?? '')), 80, ''), 'desc' => Str::limit(trim((string) ($i['desc'] ?? '')), 300, '')], $items)];
                break;
            case 'steps':
                $out += ['items' => array_map(fn ($i) => ['title' => Str::limit(trim((string) ($i['title'] ?? '')), 80, ''), 'desc' => Str::limit(trim((string) ($i['desc'] ?? '')), 300, '')], array_slice($items, 0, 6))];
                break;
            case 'gallery':
                $imgs = array_values(array_filter(array_map(fn ($u) => self::url($u), is_array($b['images'] ?? null) ? $b['images'] : [])));
                $out += ['images' => array_slice($imgs, 0, 12)];
                break;
            case 'faq':
                $out += ['items' => array_map(fn ($i) => ['q' => Str::limit(trim((string) ($i['q'] ?? '')), 160, ''), 'a' => Str::limit(trim((string) ($i['a'] ?? '')), 800, '')], $items)];
                break;
            case 'pricing':
                $out += ['intro' => $s('intro', 300), 'items' => array_map(fn ($i) => ['name' => Str::limit(trim((string) ($i['name'] ?? '')), 60, ''), 'price' => Str::limit(trim((string) ($i['price'] ?? '')), 40, ''), 'desc' => Str::limit(trim((string) ($i['desc'] ?? '')), 200, ''), 'features' => array_values(array_map(fn ($f) => Str::limit(trim((string) $f), 80, ''), array_slice(is_array($i['features'] ?? null) ? $i['features'] : [], 0, 8)))], array_slice($items, 0, 4))];
                break;
            case 'team':
                $out += ['items' => array_map(fn ($i) => ['name' => Str::limit(trim((string) ($i['name'] ?? '')), 60, ''), 'role' => Str::limit(trim((string) ($i['role'] ?? '')), 80, ''), 'bio' => Str::limit(trim((string) ($i['bio'] ?? '')), 300, ''), 'image' => self::url($i['image'] ?? null)], array_slice($items, 0, 8))];
                break;
            case 'testimonials':
                $out += ['items' => array_map(fn ($i) => ['text' => Str::limit(trim((string) ($i['text'] ?? '')), 400, ''), 'author' => Str::limit(trim((string) ($i['author'] ?? '')), 60, ''), 'role' => Str::limit(trim((string) ($i['role'] ?? '')), 80, '')], array_slice($items, 0, 6))];
                break;
            case 'stats':
                $out += ['items' => array_map(fn ($i) => ['v' => Str::limit(trim((string) ($i['v'] ?? '')), 20, ''), 'l' => Str::limit(trim((string) ($i['l'] ?? '')), 40, '')], array_slice($items, 0, 4))];
                break;
            case 'cta':
                $out += ['text' => $s('text', 300), 'button' => $s('button', 40)];
                break;
            case 'contact':
                $out += ['text' => $s('text', 300)];
                break;
            case 'video':
                $out += ['url' => self::url($b['url'] ?? null)];
                break;
        }

        return $out;
    }

    /** Bloc vide par défaut pour un type donné (Studio). */
    public static function blank(string $type): ?array
    {
        if (! isset(self::BLOCKS[$type])) {
            return null;
        }
        $defaults = [
            'text'         => ['title' => 'Un titre clair', 'body' => "Décrivez ici votre activité, votre approche ou votre histoire.\n\nUn second paragraphe pour rassurer et donner envie.", 'image_side' => 'right'],
            'features'     => ['title' => 'Nos points forts', 'intro' => '', 'items' => [['icon' => 'fa-circle-check', 'title' => 'Point fort 1', 'desc' => 'Une phrase concrète.'], ['icon' => 'fa-circle-check', 'title' => 'Point fort 2', 'desc' => 'Une phrase concrète.'], ['icon' => 'fa-circle-check', 'title' => 'Point fort 3', 'desc' => 'Une phrase concrète.']]],
            'steps'        => ['title' => 'Comment ça se passe', 'items' => [['title' => 'Étape 1', 'desc' => ''], ['title' => 'Étape 2', 'desc' => ''], ['title' => 'Étape 3', 'desc' => '']]],
            'gallery'      => ['title' => 'En images', 'images' => []],
            'faq'          => ['title' => 'Questions fréquentes', 'items' => [['q' => 'Une question fréquente ?', 'a' => 'Votre réponse.']]],
            'pricing'      => ['title' => 'Nos tarifs', 'intro' => '', 'items' => [['name' => 'Offre', 'price' => 'Sur devis', 'desc' => '', 'features' => ['Inclus 1', 'Inclus 2']]]],
            'team'         => ['title' => 'Notre équipe', 'items' => [['name' => 'Prénom Nom', 'role' => 'Rôle', 'bio' => '', 'image' => null]]],
            'testimonials' => ['title' => 'Ils nous font confiance', 'items' => [['text' => 'Un témoignage client.', 'author' => 'Prénom N.', 'role' => 'Client']]],
            'stats'        => ['title' => '', 'items' => [['v' => '+10 ans', 'l' => 'd\'expérience'], ['v' => '4.8/5', 'l' => 'Note Google'], ['v' => '100%', 'l' => 'Clients satisfaits']]],
            'cta'          => ['title' => 'Parlons de votre projet', 'text' => 'Un premier échange gratuit, sans engagement.', 'button' => ''],
            'contact'      => ['title' => 'Nous trouver', 'text' => ''],
            'video'        => ['title' => 'En vidéo', 'url' => null],
        ];

        return self::normalizeBlock(['type' => $type] + $defaults[$type]);
    }

    /** Page vide (Studio). */
    public static function blankPage(string $title): array
    {
        return self::normalizePages([[
            'title'  => $title,
            'slug'   => self::slug($title),
            'nav'    => true,
            'hero'   => ['title' => $title],
            'blocks' => [self::blank('text')],
        ]])[0];
    }

    public static function slug(string $s): string
    {
        return Str::limit(Str::slug($s), 40, '');
    }

    private static function url(mixed $u): ?string
    {
        $u = is_string($u) ? trim($u) : '';
        if ($u === '' || ! preg_match('~^(https?://|/)~i', $u) || strlen($u) > 800) {
            return null;
        }

        return $u;
    }

    private static function icon(mixed $i): string
    {
        $i = is_string($i) ? trim($i) : '';

        return preg_match('/^fa-[a-z0-9-]{2,40}$/', $i) ? $i : 'fa-circle-check';
    }
}
