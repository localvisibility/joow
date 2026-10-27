<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Articles du blog : fichiers Markdown avec en-tête (---) dans resources/content/blog. */
class Content
{
    public function posts(): array
    {
        return Cache::remember('joow:blog:index:v2:'.$this->version(), now()->addHour(), function () {
            $posts = [];
            foreach (File::glob(resource_path('content/blog/*.md')) as $file) {
                if ($p = $this->parse($file)) {
                    $posts[] = $p;
                }
            }
            usort($posts, fn ($a, $b) => strcmp($b['date'], $a['date']));

            return $posts;
        });
    }

    public function post(string $slug): ?array
    {
        $file = resource_path('content/blog/'.basename($slug).'.md');

        return is_file($file) ? $this->parse($file, withBody: true) : null;
    }

    /** Articles liés à un métier : ceux qui le citent, puis ceux des métiers de la même famille, puis les plus récents. */
    public function forSector(string $key, array $familyKeys = [], int $limit = 3): array
    {
        $posts = $this->posts();
        $direct = array_filter($posts, fn ($p) => in_array($key, $p['sectors'], true));
        $family = array_filter($posts, fn ($p) => ! in_array($key, $p['sectors'], true) && array_intersect($familyKeys, $p['sectors']));
        $generic = array_filter($posts, fn ($p) => ! in_array($key, $p['sectors'], true) && ! array_intersect($familyKeys, $p['sectors']) && in_array('service', $p['sectors'], true));

        return array_slice(array_values(array_merge($direct, $family, $generic, $posts)), 0, $limit);
    }

    /** Articles proches d'un article : plus de métiers en commun d'abord, même catégorie ensuite, puis les plus récents. */
    public function related(array $post, int $limit = 3): array
    {
        $others = array_filter($this->posts(), fn ($p) => $p['slug'] !== $post['slug']);
        usort($others, function ($a, $b) use ($post) {
            $sa = count(array_intersect($a['sectors'], $post['sectors'])) * 2 + ($a['category'] === $post['category'] ? 1 : 0);
            $sb = count(array_intersect($b['sectors'], $post['sectors'])) * 2 + ($b['category'] === $post['category'] ? 1 : 0);

            return $sb <=> $sa ?: strcmp($b['date'], $a['date']);
        });

        return array_slice(array_values($others), 0, $limit);
    }

    /** @return array<string, int> catégories et nombre d'articles */
    public function categories(): array
    {
        $out = [];
        foreach ($this->posts() as $p) {
            $out[$p['category']] = ($out[$p['category']] ?? 0) + 1;
        }
        arsort($out);

        return $out;
    }

    private function parse(string $file, bool $withBody = false): ?array
    {
        $raw = File::get($file);
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
            return null;
        }
        $meta = [];
        foreach (preg_split('/\n/', trim($m[1])) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
                $meta[$kv[1]] = trim($kv[2], " \"'");
            }
        }
        $body = trim($m[2]);
        $words = str_word_count(strip_tags($body));

        return [
            'slug'        => pathinfo($file, PATHINFO_FILENAME),
            'title'       => $meta['title'] ?? Str::headline(pathinfo($file, PATHINFO_FILENAME)),
            'description' => $meta['description'] ?? Str::limit(strip_tags(Str::markdown($body)), 155),
            'date'        => $meta['date'] ?? date('Y-m-d', filemtime($file)),
            'category'    => $meta['category'] ?? 'Conseils',
            'sectors'     => array_values(array_filter(array_map('trim', explode(',', (string) ($meta['sectors'] ?? ''))))),
            'cover'       => $meta['cover'] ?? null,
            'minutes'     => max(1, (int) round($words / 200)),
            'html'        => $withBody ? Str::markdown($body) : null,
            'excerpt'     => Str::limit(strip_tags(Str::markdown($body)), 180),
        ];
    }

    private function version(): string
    {
        $files = File::glob(resource_path('content/blog/*.md'));

        return md5(implode('|', array_map(fn ($f) => $f.filemtime($f), $files)));
    }
}
