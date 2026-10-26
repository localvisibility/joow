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
        return Cache::remember('joow:blog:index:'.$this->version(), now()->addHour(), function () {
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
