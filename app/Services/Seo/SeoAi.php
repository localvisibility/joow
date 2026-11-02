<?php

namespace App\Services\Seo;

use App\Models\Site;
use App\Services\Gemini;
use App\Services\ImageStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Optimisations SEO rédigées par l'IA : titre + description, descriptions d'images, expressions clés. */
class SeoAi
{
    private const RULES = "Règles : français impeccable, ton professionnel et naturel, aucun bourrage de mots-clés. "
        ."N'invente JAMAIS de prix, d'avis, de note, de chiffres, d'années d'expérience, de délais garantis, de labels ni de certifications : n'utilise que les informations fournies.";

    private function context(Site $site): string
    {
        $b = (array) ($site->site_data['business'] ?? []);
        $label = config("sectors.{$site->sector}.label") ?? $site->sector;

        return "Établissement : « ".($b['name'] ?? $site->name)." », $label".(($b['city'] ?? $site->city) ? ' à '.($b['city'] ?? $site->city) : '').'.'
            .(! empty($b['address']) ? " Adresse : {$b['address']}." : '');
    }

    /** Titre (≤ 60) et description (≤ 155) d'une page. */
    public function meta(Site $site, array $page): ?array
    {
        $kw = $page['keyword'] ?: 'à choisir par toi (la recherche locale la plus probable pour cette page)';
        $prompt = $this->context($site)."\n\nPage : « {$page['label']} » ({$page['url']}).\nGrand titre : ".implode(' / ', $page['h1'])."\nSous-titres : ".implode(' / ', $page['h2'])
            ."\nExpression clé visée : $kw.\nTitre actuel : {$page['title']}\nDescription actuelle : {$page['description']}\n\n"
            ."Rédige le titre SEO (balise title) et la meta description optimisés pour Google.\n"
            ."- title : 45 à 60 caractères, l'expression clé au début, la ville si pertinente, puis le nom de l'établissement après « | » s'il reste de la place.\n"
            ."- description : 120 à 155 caractères, un bénéfice concret pour le client et un appel à l'action (appeler, demander un devis, réserver).\n"
            .self::RULES."\nRéponds en JSON : {\"title\":\"…\",\"description\":\"…\"}";
        $d = $this->ask($prompt, 'SeoAi.meta');
        if (! $d || empty($d['title'])) {
            return null;
        }

        return ['title' => Str::limit(trim($d['title']), 70, ''), 'description' => Str::limit(trim((string) ($d['description'] ?? '')), 170, '')];
    }

    /** Descriptions (texte alternatif) des images sans description, en regardant réellement les images. */
    public function alts(Site $site, array $images): array
    {
        $images = array_slice(array_values($images), 0, 8);
        $parts = [['text' => $this->context($site)."\n\nVoici ".count($images)." photos du site, dans l'ordre (0, 1, 2…). Pour chacune, écris un texte alternatif SEO : "
            ."8 à 14 mots, décrit précisément ce qu'on voit, cite l'activité et si naturel la ville, sans commencer par « image de » ni « photo de ». "
            ."N'invente rien qui ne soit pas visible.\nRéponds en JSON : {\"alts\":[{\"i\":0,\"alt\":\"…\"}]}"]];
        $keep = [];
        foreach ($images as $i => $src) {
            $bytes = $this->fetch($src);
            if ($bytes) {
                $parts[] = ['text' => "Photo $i :"];
                $parts[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($bytes)]];
                $keep[$i] = $src;
            }
        }
        if (! $keep) {
            return [];
        }
        $d = $this->ask(null, 'SeoAi.alts', $parts);
        $out = [];
        foreach ((array) ($d['alts'] ?? []) as $a) {
            $i = (int) ($a['i'] ?? -1);
            if (isset($keep[$i]) && trim((string) ($a['alt'] ?? '')) !== '') {
                $out[$keep[$i]] = Str::limit(trim($a['alt']), 140, '');
            }
        }

        return $out;
    }

    /** Expressions clés locales suggérées, une par page (pas de volumes inventés). */
    public function keywords(Site $site, array $pages): array
    {
        $list = collect($pages)->map(fn ($p) => "- {$p['key']} : « {$p['label']} » — ".implode(' / ', array_slice($p['h2'], 0, 6)))->implode("\n");
        $prompt = $this->context($site)."\n\nPages du site :\n$list\n\n"
            ."Pour chaque page, propose l'expression clé principale que des clients locaux taperaient réellement dans Google pour trouver ce contenu "
            ."(2 à 5 mots, avec la ville quand c'est pertinent, pas deux pages sur la même expression), et 2 variantes secondaires. "
            ."Ajoute 5 idées d'expressions locales non encore couvertes par une page. N'indique aucun volume de recherche.\n"
            ."Réponds en JSON : {\"pages\":[{\"key\":\"index\",\"keyword\":\"…\",\"variants\":[\"…\"]}],\"ideas\":[\"…\"]}";
        $d = $this->ask($prompt, 'SeoAi.keywords');
        $keys = array_column($pages, 'key');

        return [
            'pages' => collect((array) ($d['pages'] ?? []))->filter(fn ($p) => in_array($p['key'] ?? '', $keys, true) && ! empty($p['keyword']))
                ->map(fn ($p) => ['key' => $p['key'], 'keyword' => Str::limit(trim($p['keyword']), 80, ''), 'variants' => array_slice(array_map('strval', (array) ($p['variants'] ?? [])), 0, 3)])->values()->all(),
            'ideas' => array_slice(array_map('strval', (array) ($d['ideas'] ?? [])), 0, 6),
        ];
    }

    private function ask(?string $prompt, string $ctx, ?array $parts = null): ?array
    {
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => $parts ?? [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.5, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 4000, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 90, $ctx);
            $d = json_decode((string) Gemini::text($res), true);

            return is_array($d) ? $d : null;
        } catch (\Throwable $e) {
            Log::warning("$ctx : ".$e->getMessage());

            return null;
        }
    }

    private function fetch(string $url): ?string
    {
        try {
            if (preg_match('#/media/([^/]+)/([^/?]+)#', $url, $m) && Storage::disk('local')->exists("uploads/{$m[1]}/{$m[2]}")) {
                $raw = Storage::disk('local')->get("uploads/{$m[1]}/{$m[2]}");
            } elseif (str_starts_with($url, 'http')) {
                $r = Http::timeout(12)->get($url);
                $raw = $r->successful() ? $r->body() : null;
            } else {
                return null;
            }

            return $raw ? app(ImageStore::class)->toJpeg($raw) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
