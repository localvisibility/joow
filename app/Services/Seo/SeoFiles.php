<?php

namespace App\Services\Seo;

use App\Models\Site;

/** sitemap.xml et robots.txt de chaque site client (un aperçu non payé n'est pas indexé). */
class SeoFiles
{
    public static function write(Site $site, array $pageSlugs): void
    {
        $root = rtrim(config('services.sites_path', '/var/www/sites'), '/').'/'.$site->slug;
        if (! is_dir($root)) {
            return;
        }
        $base = SeoApply::baseUrl($site);
        $online = in_array($site->status, ['paid', 'published'], true);
        $robots = $online ? "User-agent: *\nAllow: /\n\nSitemap: $base/sitemap.xml\n" : "User-agent: *\nDisallow: /\n";
        @file_put_contents($root.'/robots.txt', $robots);
        if (! $online) {
            @unlink($root.'/sitemap.xml');

            return;
        }
        $day = ($site->updated_at ?? now())->toDateString();
        $urls = array_merge([$base.'/'], array_map(fn ($p) => $base.'/'.$p.'/', array_values(array_filter($pageSlugs))));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $i => $u) {
            $xml .= '  <url><loc>'.htmlspecialchars($u, ENT_XML1).'</loc><lastmod>'.$day.'</lastmod><priority>'.($i ? '0.8' : '1.0')."</priority></url>\n";
        }
        @file_put_contents($root.'/sitemap.xml', $xml.'</urlset>'."\n");
        foreach (['robots.txt', 'sitemap.xml'] as $f) {
            @chmod($root.'/'.$f, 0666);
        }
    }
}
