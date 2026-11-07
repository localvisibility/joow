<?php

namespace App\Services;

use App\Models\Site;
use App\Services\Gemini;
use App\Services\Pages\PageSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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

    /** Propositions de logos produites pendant apply() (op logo_ai). */
    private array $logos = [];

    public const EDITABLE = ['content.', 'images.', 'booking.', 'form.', 'pages.', 'business.name', 'business.phone', 'business.address', 'business.email', 'business.opening_hours'];

    public function __construct()
    {
        $this->key = (string) config('services.gemini.key');
    }

    /**
     * @param  array  $history  Derniers échanges [{role: user|ai, text}]
     * @return array{ops: array, reply: string, applied: array, site_data: array, modules: array}
     */
    /** JSON du modèle → tableau, en tolérant les barrières ``` et du texte autour. */
    private function decode(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
        $data = json_decode(trim($clean), true);
        if (is_array($data)) {
            return $data;
        }
        if (($a = strpos($clean, '{')) !== false && ($b = strrpos($clean, '}')) !== false && $b > $a) {
            $data = json_decode(substr($clean, $a, $b - $a + 1), true);
        }

        return is_array($data) ? $data : null;
    }

    public function run(Site $site, string $instruction, array $history = []): array
    {
        $siteData = $site->site_data ?? [];
        $modules = $site->modules ?: [];

        if (! $this->key) {
            return ['ops' => [], 'applied' => [], 'reply' => "L'assistant IA n'est pas configuré pour le moment.", 'site_data' => $siteData, 'modules' => $modules];
        }

        $prompt = $this->prompt($site, $siteData, $modules, $instruction, $history);

        $data = null;
        // Deux tentatives : le modèle renvoie parfois un JSON tronqué ou vide (on journalise la cause)
        for ($attempt = 1; $attempt <= 2 && ! is_array($data); $attempt++) {
            try {
                $res = Gemini::generate([
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => $attempt === 1 ? 0.55 : 0.3, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 8192, 'thinkingConfig' => ['thinkingBudget' => 0]],
                ], 120, 'SiteAgent');
                $json = $res?->json() ?? [];
                $text = Gemini::text($res);
                $data = $this->decode($text);
                if (! is_array($data)) {
                    Log::warning("SiteAgent : réponse inexploitable (essai $attempt) HTTP ".($res?->status() ?? '-').' finish='.(data_get($json, 'candidates.0.finishReason') ?? data_get($json, 'promptFeedback.blockReason') ?? data_get($json, 'error.message') ?? '?').' texte='.Str::limit($text, 200));
                }
            } catch (\Throwable $e) {
                Log::warning("SiteAgent : exception (essai $attempt) ".$e->getMessage());
                $data = null;
            }
        }

        if (! is_array($data)) {
            return ['ops' => [], 'applied' => [], 'reply' => "Je n'ai pas réussi à traiter cette demande (le service d'IA est momentanément saturé). Réessayez dans un instant, ou formulez-la autrement, par exemple : « ajoute un bloc module menu dans la page La Carte ».", 'site_data' => $siteData, 'modules' => $modules];
        }

        $ops = is_array($data['ops'] ?? null) ? array_values(array_filter($data['ops'], 'is_array')) : [];
        [$siteData, $modules, $applied] = $this->apply($siteData, $modules, $site, $ops);

        $reply = trim((string) ($data['reply'] ?? ''));
        $claims = (bool) preg_match('/\b(j\'ai|c\'est fait|c\'est fait|voilà|modifi|ajout|chang|remplac|mis à jour|activ|supprim|créé)/iu', $reply);
        if (! count($applied) && ($ops || $claims)) {
            // Jamais de « c'est fait » quand rien n'a été appliqué
            Log::info('SiteAgent : aucune opération appliquée pour « '.Str::limit($instruction, 120).' » — ops : '.Str::limit(json_encode($ops, JSON_UNESCAPED_UNICODE), 400));
            $reply = "Je n'ai pas réussi à appliquer cette modification : rien n'a été changé sur votre site. "
                .'Reformulez en précisant l\'élément (ex. « remplace la photo du service 1 », « passe le site en fond noir », « ajoute une popup pour la soirée du 20 »), '
                .'ou faites-le directement : cliquez sur l\'élément dans l\'aperçu (texte ou bouton « Changer la photo »), onglet Style pour les couleurs et le fond, onglet Modules pour la popup.';
        } elseif ($reply === '') {
            $reply = count($applied) ? 'C\'est fait.' : 'Je n\'ai rien modifié. Précisez ce que vous souhaitez changer ?';
        } elseif (count($ops) > count($applied) && count($applied)) {
            $reply .= ' (Une partie de la demande n\'a pas pu être appliquée : vérifiez l\'aperçu.)';
        }

        return ['ops' => $ops, 'applied' => $applied, 'reply' => Str::limit($reply, 600, '…'), 'site_data' => $siteData, 'modules' => $modules, 'logos' => $this->logos];
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
                        if (! empty($op['hero_style']) && in_array($op['hero_style'], ['editorial', 'split', 'center', 'bright', 'poster'], true)) {
                            $d['hero_style'] = $op['hero_style'];
                            $applied[] = 'Mise en page du hero : '.$op['hero_style'];
                        }
                        break;

                    case 'brand':
                        $brand = $d['brand'] ?? [];
                        if (! empty($op['bg']) && in_array($op['bg'], LogoStudio::BG, true)) { $brand['bg'] = $op['bg']; }
                        if (! empty($op['size']) && in_array($op['size'], LogoStudio::SIZES, true)) { $brand['size'] = $op['size']; }
                        foreach (['invert', 'show_name', 'hide'] as $k) { if (array_key_exists($k, $op)) { $brand[$k] = (bool) $op[$k]; } }
                        $d['brand'] = $brand;
                        $applied[] = 'Logo : '.(! empty($op['hide']) ? 'retiré' : 'affichage ajusté');
                        break;
                    case 'logo_ai':
                        $this->logos = app(LogoStudio::class)->generate($site, (string) ($op['style'] ?? 'épuré'), (string) ($op['hint'] ?? ''));
                        if ($this->logos) { $applied[] = count($this->logos).' propositions de logo'; }
                        break;
                    case 'image_fit':
                        // Afficher une image en entier (menu, affiche) ou recadrée : par URL ou par chemin (images.hero, content.services.0.image…)
                        $url = (string) ($op['url'] ?? '');
                        if ($url === '' && ! empty($op['path']) && is_string($p = Arr::get($d, (string) $op['path']))) {
                            $url = $p;
                        }
                        if ($url !== '' && in_array($op['fit'] ?? '', ['contain', 'cover'], true)) {
                            $fit = (array) ($d['image_fit'] ?? []);
                            if ($op['fit'] === 'contain') {
                                $fit[$url] = 'contain';
                            } else {
                                unset($fit[$url]);
                            }
                            $d['image_fit'] = $fit;
                            $applied[] = $op['fit'] === 'contain' ? 'Image affichée en entier' : 'Image recadrée';
                        }
                        break;

                    case 'module_config':
                        // Réglages d'un module (popup, bandeau d'annonce, assistant) + activation
                        $key = (string) ($op['key'] ?? '');
                        $rules = \App\Http\Controllers\SiteEditorController::MODULE_SETTINGS[$key] ?? null;
                        if (! $rules || ! is_array($op['config'] ?? null)) {
                            break;
                        }
                        $cur = is_array($modules[$key] ?? null) ? $modules[$key] : ['enabled' => (bool) ($modules[$key] ?? false)];
                        foreach ($op['config'] as $k => $v) {
                            $rule = $rules[$k] ?? null;
                            if ($rule === 'bool') {
                                $cur[$k] = (bool) $v;
                            } elseif (is_int($rule)) {
                                $v = mb_substr(strip_tags((string) $v), 0, $rule);
                                if (in_array($k, ['image', 'button_url', 'link'], true) && $v !== '' && ! preg_match('#^(https?://|/|\#|tel:|mailto:)#i', $v)) {
                                    continue;
                                }
                                $cur[$k] = $v;
                            }
                        }
                        $cur['enabled'] = array_key_exists('enabled', $op) ? (bool) $op['enabled'] : true;
                        $modules[$key] = $cur;
                        $applied[] = 'Module « '.(config("modules.$key.name") ?? $key).' » '.($cur['enabled'] ? 'configuré et activé' : 'configuré (désactivé)');
                        break;

                    case 'module':
                        $key = (string) ($op['key'] ?? '');
                        if (array_key_exists($key, config('modules')) && array_key_exists('enabled', $op)) {
                            // Garde les réglages existants du module (popup, assistant…) : seul l'état change
                            $modules[$key] = is_array($modules[$key] ?? null) ? array_merge($modules[$key], ['enabled' => (bool) $op['enabled']]) : (bool) $op['enabled'];
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
            'logo'          => LogoStudio::settings($d) + ['present' => ! empty(LogoStudio::settings($d)['logo'])],
            'content'       => $content,
            'images'        => $d['images'] ?? [],
            'photos_disponibles' => $photos,
            'sections_accueil' => ['ids' => ['services', 'process', 'menu', 'rooms', 'gallery', 'about', 'reviews', 'faq', 'booking', 'cta', 'contact'], 'config' => $d['content']['sections'] ?? null],
            'pages'         => array_map(fn ($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'nav' => $p['nav'], 'hero' => $p['hero'], 'blocks' => array_map(fn ($bl, $i) => ['index' => $i] + $bl, $p['blocks'], array_keys($p['blocks']))], $pages),
            'modules'       => collect(config('modules'))->map(fn ($m, $k) => ['key' => $k, 'label' => $m['name'] ?? $k, 'enabled' => is_array($modules[$k] ?? null) ? (bool) ($modules[$k]['enabled'] ?? false) : (bool) ($modules[$k] ?? false)]
                + (in_array($k, ['popup', 'announce'], true) && is_array($modules[$k] ?? null) ? ['config' => Arr::except($modules[$k], ['enabled'])] : []))->values()->all(),
            'images_entieres' => array_keys((array) ($d['image_fit'] ?? [])),
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
- Les modules natifs (carte/menu, réservation, chambres, avis Google, galerie) PEUVENT être insérés dans n'importe quelle page avec un bloc {"type":"module","key":"menu|booking|rooms|reviews|gallery"} : c'est la bonne réponse quand le client veut « le module X dans la page Y ». Ne refuse jamais en disant que les modules sont réservés à l'accueil.
- Une page = 3 à 6 blocs variés, avec un bloc "cta" ou "contact" à la fin. Slugs courts en minuscules (ex. "nos-realisations").
- "reply" : 1 à 3 phrases, à la première personne, ce que tu as fait (et une suggestion utile). Si tu ne peux pas, dis pourquoi et propose une alternative. N'invente jamais une action non listée dans ops : chaque chose annoncée dans "reply" DOIT correspondre à une opération de "ops", sinon dis clairement que tu ne l'as pas faite.
- Tu ne peux PAS importer un fichier ou une photo envoyée par le client dans le chat : demande-lui de cliquer sur l'image dans l'aperçu (bouton « Changer la photo ») ou sur « Ajouter un PDF ou une image » ; tu peux en revanche utiliser les URL de "photos_disponibles" ou une URL qu'il te donne.

OPÉRATIONS DISPONIBLES (champ "op")
- {"op":"set","path":"content.hero_title","value":"..."} — chemins autorisés : content.* (hero_title, hero_subtitle, tagline, about_title, about_p1, about_p2, badges[], stats[{v,l}], services[{name,desc,price,image}], services_title, services_intro, faq[{q,a}], faq_title, process[{title,desc}], process_title, cta_text, cta_label, cta_band_title, gallery_title, reviews_title, contact_title), images.hero / images.about / images.services / images.gallery[], booking.title / booking.sub, business.name / business.phone / business.address / business.email / business.opening_hours[], pages.N.hero.title / pages.N.hero.image etc.
  PHOTOS DES SERVICES : la grande carte du 1er service (« 01 », « Le plus demandé ») utilise images.services ; les autres cartes utilisent content.services.N.image (N = position, à partir de 0). Pour changer « la photo du service 2 » → content.services.1.image.
- {"op":"image_fit","path":"images.services","fit":"contain|cover"} (ou "url":"…") — "contain" = afficher l'image ENTIÈRE sans la recadrer (menu A4, affiche, flyer, logo) ; "cover" = la recadrer pour remplir le cadre.
- {"op":"module_config","key":"popup","enabled":true,"config":{"title":"…","text":"…","image":"url ou vide","button_label":"Réserver","button_url":"#reserver","delay":"3","frequency":"session|day|always","start":"AAAA-MM-JJ","end":"AAAA-MM-JJ"}} — POPUP à l'arrivée sur le site (offre, événement, fermeture, nouvelle carte).
- {"op":"module_config","key":"announce","enabled":true,"config":{"text":"…","link":"#contact","link_label":"En savoir plus","style":"accent|dark"}} — BANDEAU d'annonce en haut de toutes les pages.
- {"op":"module_config","key":"bot","config":{"welcome":"…","practical":"…","faq":"…","instructions":"…"}} — réglages de l'assistant du site.
- FOND / AMBIANCE : « fond noir », « site sombre », « ambiance foncée » → {"op":"style","theme":"dark"} ; « fond blanc », « clair » → {"op":"style","theme":"light"}.
- MENU / CARTE / BROCHURE en PDF ou en image A4 → bloc {"type":"document","title":"Notre carte","text":"…","file":"URL du PDF ou de l'image","button":"Voir la carte"} (si le client n'a pas encore envoyé le fichier, crée le bloc avec "file": null et dis-lui de cliquer sur « Ajouter un PDF ou une image » dans ce bloc). Pour une image dans un bloc texte : "image_ratio":"full" l'affiche en entier.
- {"op":"add_page","page":{"slug":"...","title":"...","nav":true,"hero":{"tag":"...","title":"...","subtitle":"..."},"blocks":[{"type":"text","title":"...","body":"paragraphe 1\\n\\nparagraphe 2","image":"url ou null","image_side":"right"}, ...]}}
- {"op":"update_page","slug":"...","patch":{"title":"...","nav":true,"hero":{...}}}
- {"op":"remove_page","slug":"..."}
- {"op":"add_block","page":"slug","index":2,"block":{...}}   (index optionnel = position)
- {"op":"update_block","page":"slug","index":0,"block":{champs à modifier}}
- {"op":"remove_block","page":"slug","index":0}
- {"op":"move_block","page":"slug","from":0,"to":2}
- {"op":"sections","order":["services","gallery",...],"hidden":{"faq":true}} — réorganiser / masquer des sections de l'accueil
- {"op":"style","accent":"#hex","font":"Playfair Display|Space Grotesk|DM Serif Display|Sora|Poppins|Montserrat|Cormorant Garamond","theme":"light|dark","hero_style":"editorial|split|center|bright|poster"} (bright = en-tête clair lumineux avec collage photo, poster = grande typographie sur fond de couleur avec bande de photos)
- {"op":"module","key":"whatsapp|bot|reviews|legal|booking|restaurant|rooms|menu|payment|stats|popup|announce","enabled":true}
- {"op":"brand","bg":"auto|none|light|dark|accent","size":"sm|md|lg","invert":true|false,"show_name":true|false,"hide":true|false} — LOGO de l'en-tête : "bg" = fond derrière le logo (auto = choisi selon la clarté du logo), "invert" = inverse les couleurs d'un logo (blanc ↔ noir), "show_name" = afficher le nom à côté, "hide" = retirer le logo (l'icône du métier le remplace). Un logo blanc invisible sur fond clair → bg "dark" ou invert true. « Logo en double » = le nom apparaît à côté d'un logo qui le contient déjà → show_name false.
- {"op":"logo_ai","style":"épuré|élégant|moderne|artisanal|chaleureux|premium|ludique","hint":"précisions du client"} — GÉNÈRE 4 propositions de logo vectoriel (2 crédits) ; le client choisit ensuite dans le Studio. À utiliser quand il demande un nouveau logo, un meilleur logo, ou de refaire/générer le logo. Ne jamais tenter de "générer un logo" par une autre opération.

TYPES DE BLOCS (pages)
{$blocks}
Champs des blocs : text{title,body,image,image_side,image_ratio} · document{title,text,file,button} · features{title,intro,items[{icon,title,desc}]} · steps{title,items[{title,desc}]} · gallery{title,images[]} · faq{title,items[{q,a}]} · pricing{title,intro,items[{name,price,desc,features[]}]} · team{title,items[{name,role,bio,image}]} · testimonials{title,items[{text,author,role}]} · stats{title,items[{v,l}]} · cta{title,text,button} · contact{title,text} · video{title,url}

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
