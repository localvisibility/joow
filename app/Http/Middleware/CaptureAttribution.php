<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Mémorise l'origine de la visite (utm_*, fbclid, gclid, referer) en session : premier contact conservé, dernier contact mis à jour. */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next)
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid'];
        $touch = array_filter(array_intersect_key($request->query(), array_flip($keys)), fn ($v) => is_string($v) && $v !== '');
        if ($touch && $request->hasSession()) {
            $touch = array_map(fn ($v) => mb_substr($v, 0, 200), $touch);
            $touch['at'] = now()->toIso8601String();
            $touch['landing'] = mb_substr($request->path(), 0, 120);
            if ($ref = $request->headers->get('referer')) {
                $touch['referer'] = mb_substr(parse_url($ref, PHP_URL_HOST) ?: $ref, 0, 120);
            }
            $attr = (array) $request->session()->get('attribution', []);
            $attr['first'] ??= $touch;
            $attr['last'] = $touch;
            if (! empty($touch['fbclid'])) {
                $attr['fbc'] = 'fb.1.'.(int) (microtime(true) * 1000).'.'.$touch['fbclid']; // format attendu par l'API de conversions
            }
            $request->session()->put('attribution', $attr);
        }

        return $next($request);
    }
}
