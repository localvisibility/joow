<?php

namespace App\Services;

use App\Models\Site;
use App\Services\Pages\PageSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Agent IA du Studio : transforme une demande en langage naturel en une liste
 * d'opérations structurées (textes, images, pages, blocs, sections, style,
 * modules), les applique au site et explique ce qui a été fait.
 *
 * Contrairement à un simple "réécris le JSON", l'agent manipule tout le site
 * (accueil + pages) par opérations atomiques, ce qui permet des demandes larges
 * (« crée une page Nos réalisations avec une galerie et une FAQ ») comme des
 * retouches fines (« mets le titre en plus court »).
 */
class SiteAgent
{
    private string $key;

    public const EDITABLE = ['content.', 'images.', 'booking.', 'form.', 'pages.', 'business.name', 'business.phone', 'business.address', 'business.email', 'business.opening_hours'];

    public function __construct()
    {
        $this->key = (string) config('services.gemini.key');
    }

    /**
     * @param  array  $history  Derniers échanges [{role: user|ai, text}]
     * @return array{ops: array, reply: string, applied: array, site_data: array, modules: array}
     */
    public function run(Site $site, string $instruction, array $history = []): array
    {
        $siteData = $site->site_data ?? [];
        $modules = $site->modules ?: [];

        if (! $this->key) {
            return ['ops' => [], 'applied' => [], 'reply' => "L'assistant IA n'est pas configuré pour le moment.", 'site_data' => $siteData, 'modules' => $modules];
        }

        $prompt = $this->prompt($site, $siteData, $modules, $instruction, $history);

        try {
            $res = Http::timeout(120)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key='.$this->key,
                [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.55, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 8192, 'thinkingConfig' => ['thinkingBudget' => 0]],
                ]
            );
            $text = data_get($res->json(), 'candidates.0.content.parts.0.text');
            $data = $text ? json_decode($text, true) : null;
        } catch (\Throwable $e) {
            $data = null;
        }

        if (! is_array($data)) {
            return ['ops' => [], 'applied' => [], 'reply' => "Je n'ai pas réussi à traiter cette demande. Pouvez-vous la reformuler ?", 'site_data' => $siteData, 'modules' => $modules];
        }

        $ops = is_array($data['ops'] ?? null) ? array_values(array_filter($data['ops'], 'is_array')) : [];
        [$siteData, $modules, $applied] = $this->apply($siteData, $modules, $site, $ops);

        $reply = trim((string) ($data['reply'] ?? ''));
        if ($reply === '') {
            $reply = count($applied) ? 'C\'est fait.' : 'Je n\'ai rien modifié. Précisez ce que vous souhaitez changer ?';
        }

        return ['ops' => $ops, 'applied' => $applied, 'reply' => Str::limit($reply, 600, '…'), 'site_data' => $siteData, 'modules' => $modules];
    }

    /** Applique les opérations ; renvoie [site_data, modules, libellés des changements]. */
    public function apply(array $d, array $modules, Site $site, array $ops): array
    {
        $applied = [];
        $pages = PageSchema::normalizePages($d['pages'] ?? []);
        $findPage = function (string $slug) use (&$pages) {
            $slug = PageSchema::slug($slug);
            foreach ($pages as $i => $p) {
                if ($p['slug'] === $slug) {
                    return $i;
                }
            }
            return null;
        };

        foreach (array_slice($ops, 0, 40) as $op) {
            $type = (string) ($op['op'] ?? $op['type'] ?? '');
            try {
                switch ($type) {
                    case 'set':
                        $path = (string) ($op['path'] ?? '');
                        if (! $this->editable($path) || ! array_key_exists('value', $op)) {
                            break;
                        }
                        if (Str::startsWith($path, 'pages')) {
                            // Les pages passent par le normaliseur : on applique sur la copie puis on renormalise
                            $tmp = ['pages' => $pages];
                            Arr::set($tmp, $path, $this->clean($op['value']));
                            $pages = PageSchema::normalizePages($tmp['pages']);
                        } else {
                            Arr::set($d, $path, $this->clean($op['value']));
                        }
                        $applied[] = $this->labelFor($path);
                        break;

                    case 'add_page':
                        if (count($pages) >= PageSchema::MAX_PAGES) {
                            break;
                        }
                        $p = is_array($op['page'] ?? null) ? $op['page'] : Arr::except($op, ['op', 'type']);
                        $new = PageSchema::normalizePages(array_merge($pages, [$p]));
                        if (count($new) > count($pages)) {
                            $pages = $new;
                            $applied[] = 'Page « '.end($pages)['title'].' » créée ('.count(end($pages)['blocks']).' blocs)';
                        }
                        break;

                    case 'update_page':
                        $i = $findPage((string) ($op['slug'] ?? ''));
                        if ($i === null) {
                            break;
                        }
                        $patch = is_array($op['patch'] ?? null) ? $op['patch'] : Arr::only($op, ['title', 'nav', 'seo', 'hero']);
                        foreach (Arr::only($patch, ['title', 'nav', 'seo']) as $k => $v) {
                            $pages[$i][$k] = $v;
                        }
                        if (is_array($patch['hero'] ?? null)) {
                            $pages[$i]['hero'] = array_merge($pages[$i]['hero'], $patch['hero']);
                        }
                        $pages = PageSchema::normalizePages($pages);
                        $applied[] = 'Page « '.$pages[$i]['title'].' » mise à jour';
                        break;

                    case 'remove_page':
                        $i = $findPage((string) ($op['slug'] ?? ''));
                        if ($i === null) {
                            break;
                        }
                        $applied[] = 'Page « '.$pages[$i]['title'].' » supprimée';
                        array_splice($pages, $i, 1);
                        break;

                    case 'add_block':
                        $i = $findPage((string) ($op['page'] ?? ''));
                        $b = PageSchema::normalizeBlock($op['block'] ?? null);
                        if ($i === null || ! $b || count($pages[$i]['blocks']) >= PageSchema::MAX_BLOCKS) {
                            break;
                        }
                        $at = isset($op['index']) ? max(0, min((int) $op['index'], count($pages[$i]['blocks']))) : count($pages[$i]['blocks']);
                        array_splice($pages[$i]['blocks'], $at, 0, [$b]);
                        $applied[] = 'Bloc « '.(PageSchema::BLOCKS[$b['type']]['label']).' » ajouté à « '.$pages[$i]['title'].' »';
                        break;

                    case 'update_block':
                        $i = $findPage((string) ($op['page'] ?? ''));
                        $j = (int) ($op['index'] ?? -1);
                        if ($i === null || ! isset($pages[$i]['blocks'][$j])) {
                            break;
                        }
                        $merged = PageSchema::normalizeBlock(array_merge($pages[$i]['blocks'][$j], is_array($op['block'] ?? null) ? $op['block'] : (is_array($op['patch'] ?? null) ? $op['patch'] : [])));
                        if ($merged) {
                            $pages[$i]['blocks'][$j] = $merged;
                            $applied[] = 'Bloc '.($j + 1).' de « '.$pages[$i]['title'].' » modifié';
                        }
                        break;

                    case 'remove_block':
                        $i = $findPage((string) ($op['page'] ?? ''));
                        $j = (int) ($op['index'] ?? -1);
                        if ($i === null || ! isset($pages[$i]['blocks'][$j])) {
                            break;
                        }
                        array_splice($pages[$i]['blocks'], $j, 1);
                        $applied[] = 'Bloc '.($j + 1).' de « '.$pages[$i]['title'].' » supprimé';
                        break;

                    case 'move_block':
                        $i = $findPage((string) ($op['page'] ?? ''));
                        $from = (int) ($op['from'] ?? -1);
                        $to = (int) ($op['to'] ?? -1);
                        if ($i === null || ! isset($pages[$i]['blocks'][$from]) || $to < 0 || $to >= count($pages[$i]['blocks'])) {
                            break;
                        }
                        $blk = $pages[$i]['blocks'][$from];
                        array_splice($pages[$i]['blocks'], $from, 1);
                        array_splice($pages[$i]['blocks'], $to, 0, [$blk]);
                        $applied[] = 'Blocs réordonnés sur « '.$pages[$i]['title'].' »';
                        break;

                    case 'sections':
                        $cur = $d['content']['sections'] ?? ['order' => [], 'hidden' => []];
                        if (is_array($op['order'] ?? null)) {
                            $cur['order'] = array_values(array_filter(array_map('strval', $op['order']), fn ($s) => preg_match('/^[a-z]+$/', $s)));
                        }
                        if (is_array($op['hidden'] ?? null)) {
                            foreach ($op['hidden'] as $k => $v) {
                                if (preg_match('/^[a-z]+$/', (string) $k)) {
                                    $cur['hidden'][$k] = (bool) $v;
                                }
                            }
                        }
                        Arr::set($d, 'content.sections', $cur);
                        $applied[] = 'Sections de l\'accueil réorganisées';
                        break;

                    case 'style':
                        if (! empty($op['accent']) && preg_match('/^#?[0-9a-fA-F]{6}$/', trim($op['accent']))) {
                            $d['accent'] = '#'.ltrim(trim($op['accent']), '#');
                            $applied[] = 'Couleur principale : '.$d['accent'];
                        }
                        if (! empty($op['font']) && in_array($op['font'], ['Space Grotesk', 'Playfair Display', 'DM Serif Display', 'Sora', 'Poppins', 'Montserrat', 'Cormorant Garamond'], true)) {
                            $d['font'] = $op['font'];
                            $applied[] = 'Police : '.$op['font'];
                        }
                        if (! empty($op['theme']) && in_array($op['theme'], ['light', 'dark'], true)) {
                            $d['theme'] = $op['theme'];
                            $applied[] = 'Thème '.($op['theme'] === 'dark' ? 'sombre' : 'clair');
                        }
                        if (! empty($op['hero_style']) && in_array($op['hero_style'], ['editorial', 'split', 'center'], true)) {
                            $d['hero_style'] = $op['hero_style'];
                            $applied[] = 'Mise en page du hero : '.$op['hero_style'];
                        }
                        break;

                    case 'module':
                        $key = (string) ($op['key'] ?? '');
                        if (array_key_exists($key, config('modules')) && array_key_exists('enabled', $op)) {
                            $modules[$key] = (bool) $op['enabled'];
                            $applied[] = 'Module « '.(config("modules.$key.name") ?? $key).' » '.($op['enabled'] ? 'activé' : 'désactivé');
                        }
                        break;
                }
            } catch (\Throwable $e) {
                // opération ignorée
            }
        }

        $d['pages'] = $pages;

        return [$d, $modules, array_values(array_unique($applied))];
    }

    // ───────────────────────────── prompt ─────────────────────────────

    private function prompt(Site $site, array $d, array $modules, string $instruction, array $history): string
    {
        $b = $d['business'] ?? [];
        $sector = $site->sector ?: 'service';
        $label = config("sectors.$sector.label") ?? 'Services';
        $content = Arr::except($d['content'] ?? [], ['sections']);
        $pages = PageSchema::normalizePages($d['pages'] ?? []);
        $photos = array_slice(array_values(array_filter($b['photos'] ?? [])), 0, 8);

        $summary = [
            'etablissement' => Arr::only($b, ['name', 'city', 'address', 'phone', 'rating', 'reviews_count', 'opening_hours']),
            'secteur'       => $label,
            'style'         => ['accent' => $d['accent'] ?? config("sectors.$sector.color"), 'font' => $d['font'] ?? null, 'theme' => $d['theme'] ?? null, 'hero_style' => $d['hero_style'] ?? null],
            'content'       => $content,
            'images'        => $d['images'] ?? [],
            'photos_disponibles' => $photos,
            'sections_accueil' => ['ids' => ['services', 'process', 'menu', 'rooms', 'gallery', 'about', 'reviews', 'faq', 'booking', 'cta', 'contact'], 'config' => $d['content']['sections'] ?? null],
            'pages'         => array_map(fn ($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'nav' => $p['nav'], 'hero' => $p['hero'], 'blocks' => array_map(fn ($bl, $i) => ['index' => $i] + $bl, $p['blocks'], array_keys($p['blocks']))], $pages),
            'modules'       => collect(config('modules'))->map(fn ($m, $k) => ['key' => $k, 'label' => $m['label'] ?? $k, 'enabled' => (bool) ($modules[$k] ?? false)])->values()->all(),
            'booking'       => $d['booking'] ?? [],
        ];
        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $blocks = collect(PageSchema::BLOCKS)->map(fn ($v, $k) => "- $k : {$v['label']} — {$v['desc']}")->implode("\n");
        $hist = collect($history)->take(-8)->map(fn ($m) => (($m['role'] ?? '') === 'user' ? 'CLIENT' : 'ASSISTANT').' : '.Str::limit((string) ($m['text'] ?? ''), 400, '…'))->implode("\n");

        return <<<TXT
Tu es l'agent IA du Studio Joow : tu modifies le site vitrine de "{$b['name']}" ({$label}, {$b['city']}) à la demande de son propriétaire.
Tu reçois l'ÉTAT DU SITE (JSON) et la DEMANDE. Tu réponds UNIQUEMENT avec un JSON : {"reply": "...", "ops": [...]}.

RÈGLES
- Applique exactement la demande, sans toucher au reste. Si la demande est vague (« améliore mon site »), fais 3 à 6 améliorations concrètes et explique-les.
- Rédige en français, ton professionnel et chaleureux, orienté conversion. Jamais de fausses infos légales, de faux prix précis ni de faux avis clients.
- Photos : utilise en priorité "photos_disponibles" (vraies photos de l'établissement). Pas d'autres URL d'images sauf si le client en donne une.
- Une page = 3 à 6 blocs variés, avec un bloc "cta" ou "contact" à la fin. Slugs courts en minuscules (ex. "nos-realisations").
- "reply" : 1 à 3 phrases, à la première personne, ce que tu as fait (et une suggestion utile). Si tu ne peux pas, dis pourquoi et propose une alternative. N'invente jamais une action non listée dans ops.

OPÉRATIONS DISPONIBLES (champ "op")
- {"op":"set","path":"content.hero_title","value":"..."} — chemins autorisés : content.* (hero_title, hero_subtitle, tagline, about_title, about_p1, about_p2, badges[], stats[{v,l}], services[{name,desc,price}], services_title, services_intro, faq[{q,a}], faq_title, process[{title,desc}], process_title, cta_text, cta_label, cta_band_title, gallery_title, reviews_title, contact_title), images.hero / images.about / images.services / images.gallery[], booking.title / booking.sub, business.name / business.phone / business.address / business.email / business.opening_hours[], pages.N.hero.title etc.
- {"op":"add_page","page":{"slug":"...","title":"...","nav":true,"hero":{"tag":"...","title":"...","subtitle":"..."},"blocks":[{"type":"text","title":"...","body":"paragraphe 1\\n\\nparagraphe 2","image":"url ou null","image_side":"right"}, ...]}}
- {"op":"update_page","slug":"...","patch":{"title":"...","nav":true,"hero":{...}}}
- {"op":"remove_page","slug":"..."}
- {"op":"add_block","page":"slug","index":2,"block":{...}}   (index optionnel = position)
- {"op":"update_block","page":"slug","index":0,"block":{champs à modifier}}
- {"op":"remove_block","page":"slug","index":0}
- {"op":"move_block","page":"slug","from":0,"to":2}
- {"op":"sections","order":["services","gallery",...],"hidden":{"faq":true}} — réorganiser / masquer des sections de l'accueil
- {"op":"style","accent":"#hex","font":"Playfair Display|Space Grotesk|DM Serif Display|Sora|Poppins|Montserrat|Cormorant Garamond","theme":"light|dark","hero_style":"editorial|split|center"}
- {"op":"module","key":"whatsapp|bot|reviews|legal|booking|restaurant|rooms|menu|payment|stats","enabled":true}

TYPES DE BLOCS (pages)
{$blocks}
Champs des blocs : text{title,body,image,image_side} · features{title,intro,items[{icon,title,desc}]} · steps{title,items[{title,desc}]} · gallery{title,images[]} · faq{title,items[{q,a}]} · pricing{title,intro,items[{name,price,desc,features[]}]} · team{title,items[{name,role,bio,image}]} · testimonials{title,items[{text,author,role}]} · stats{title,items[{v,l}]} · cta{title,text,button} · contact{title,text} · video{title,url}

ÉTAT DU SITE
{$json}

HISTORIQUE RÉCENT
{$hist}

DEMANDE DU CLIENT
{$instruction}
TXT;
    }

    private function editable(string $path): bool
    {
        if (! preg_match('/^[a-z0-9_.]+$/i', $path)) {
            return false;
        }
        foreach (self::EDITABLE as $p) {
            if ($path === rtrim($p, '.') || Str::startsWith($path, $p)) {
                return true;
            }
        }

        return false;
    }

    private function clean(mixed $v): mixed
    {
        if (is_string($v)) {
            return Str::limit(trim(strip_tags($v)), 5000, '');
        }
        if (is_array($v)) {
            return array_map(fn ($x) => $this->clean($x), $v);
        }

        return is_bool($v) || is_numeric($v) || $v === null ? $v : (string) $v;
    }

    private function labelFor(string $path): string
    {
        $map = ['content.hero_title' => 'Titre principal', 'content.hero_subtitle' => 'Sous-titre', 'content.tagline' => 'Surtitre', 'content.about_p1' => 'À propos', 'content.about_p2' => 'À propos', 'content.about_title' => 'Titre À propos', 'content.services' => 'Services', 'content.faq' => 'FAQ', 'content.badges' => 'Points forts', 'content.stats' => 'Chiffres clés', 'content.process' => 'Parcours', 'content.cta_text' => 'Texte d\'appel', 'content.cta_label' => 'Bouton principal', 'content.cta_band_title' => 'Bande d\'appel à l\'action', 'images.hero' => 'Photo de fond', 'images.about' => 'Photo À propos', 'images.gallery' => 'Galerie', 'booking' => 'Réservation', 'business' => 'Coordonnées', 'form' => 'Formulaire'];
        foreach ($map as $k => $l) {
            if ($path === $k || Str::startsWith($path, $k.'.')) {
                return $l.' modifié'.(Str::endsWith($l, 's') ? 's' : '');
            }
        }
        if (Str::startsWith($path, 'pages.')) {
            return 'Page modifiée';
        }

        return 'Contenu modifié';
    }
}
