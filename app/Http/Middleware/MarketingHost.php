<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** www.joow.fr → joow.fr (301), et drapeau « hôte vitrine » pour le routage de l'accueil. */
class MarketingHost
{
    public static function isMarketing(Request $request): bool
    {
        $host = strtolower($request->getHost());
        $apex = strtolower((string) config('marketing.host', 'joow.fr'));

        return $host === $apex || $host === 'www.'.$apex;
    }

    public function handle(Request $request, Closure $next)
    {
        $apex = strtolower((string) config('marketing.host', 'joow.fr'));
        if (strtolower($request->getHost()) === 'www.'.$apex) {
            return redirect()->to('https://'.$apex.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
