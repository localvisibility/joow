<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Accounts;
use App\Services\SiteRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Détache un ancien site Local Visibility de l'ancien serveur :
 * 1) photos et scripts rapatriés sur Joow (servis depuis le site lui-même, /lv-assets/…),
 *    mentions légales figées dans un fichier local (hébergeur mis à jour) ;
 * 2) traceur de visites Joow ajouté (statistiques du tableau de bord) ;
 * 3) option --invite : compte Joow du propriétaire + lien de connexion vers ses réservations.
 */
class LocalizeLvSite extends Command
{
    protected $signature = 'lv:localize {lv_slug : identifiant du site chez Local Visibility}
        {--site= : slug du site dans Joow (par défaut : le même)} {--invite= : e-mail du propriétaire à qui envoyer son accès Joow} {--dry-run}';

    protected $description = 'Rapatrie photos et mentions légales d\'un ancien site Local Visibility, ajoute les statistiques Joow et invite le propriétaire';

    private const OLD = 'https://local-visibility.com';

    public function handle(SiteRenderer $renderer, Accounts $accounts): int
    {
        $lv = (string) $this->argument('lv_slug');
        $dry = (bool) $this->option('dry-run');
        $site = Site::where('slug', $this->option('site') ?: $lv)->first();
        if (! $site) {
            $this->error('Site Joow introuvable : précisez --site=<slug>.');

            return self::FAILURE;
        }
        $root = rtrim((string) config('services.sites_path', '/var/www/sites'), '/').'/'.$site->slug;
        $file = $root.'/index.html';
        $html = is_file($file) ? (string) file_get_contents($file) : (string) $site->html_content;
        if ($html === '') {
            $this->error('Page introuvable ('.$file.').');

            return self::FAILURE;
        }
        $this->info("Site : {$site->name} ({$site->slug})");

        // 1) Fichiers de l'ancien serveur (photos, scripts) → /lv-assets/… sur le site
        preg_match_all('#https://local-visibility\.com(/(?:previews|widgets|images|assets|uploads)/[^"\'\s)<>]+)#i', $html, $m);
        $urls = array_values(array_unique($m[0]));
        $ok = 0;
        $failed = [];
        foreach ($urls as $url) {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            if (! preg_match('#\.[a-z0-9]{2,5}$#i', $path)) {
                continue; // lien vers une page, pas un fichier
            }
            $local = '/lv-assets'.$path;
            if (! $dry) {
                if (! is_file($root.$local)) {
                    try {
                        $res = Http::timeout(30)->get($url);
                    } catch (\Throwable) {
                        $res = null;
                    }
                    if (! $res || ! $res->successful() || strlen($res->body()) < 20) {
                        $failed[] = $url;

                        continue;
                    }
                    $body = $res->body();
                    if (str_ends_with($path, '/legal.js')) {
                        $body = $this->legalScript($body, $root, $html);
                    }
                    @mkdir(dirname($root.$local), 0777, true);
                    file_put_contents($root.$local, $body);
                    @chmod($root.$local, 0666);
                }
            }
            $html = preg_replace('#'.preg_quote($url, '#').'(\?[^"\'\s)<>]*)?#', $local, $html);
            $ok++;
        }
        $this->line("  $ok fichier(s) rapatrié(s) sur Joow".($failed ? ' · '.count($failed).' introuvable(s) sur l\'ancien serveur' : ''));
        foreach (array_slice($failed, 0, 5) as $f) {
            $this->warn('    '.$f);
        }

        // 2) Traceur de visites Joow (statistiques du tableau de bord)
        if (! str_contains($html, 'data-joow-stats')) {
            $api = rtrim((string) config('app.url'), '/').'/api/site/'.$site->slug;
            $tracker = '<script data-joow-stats>try{fetch('.json_encode($api.'/view', JSON_UNESCAPED_SLASHES).',{method:"POST",headers:{"Content-Type":"application/json"},body:"{}",keepalive:true}).catch(function(){});}catch(e){}</script>';
            $html = preg_replace('#</body>#i', $tracker."\n</body>", $html, 1);
            $this->line('  Traceur de visites Joow ajouté');
        }

        $left = substr_count($html, 'local-visibility.com/');
        if ($left) {
            $this->warn("  $left lien(s) vers l'ancien serveur restent dans la page (liens de navigation, pas des fichiers).");
        }

        if (! $dry) {
            file_put_contents($file, $html);
            @chmod($file, 0666);
            $site->forceFill(['html_content' => $html])->saveQuietly();
        }

        // 3) Accès du propriétaire
        if ($email = $this->option('invite')) {
            $this->line("  Accès Joow envoyé à : $email");
            if (! $dry) {
                [$user] = $accounts->ensure($email, [$site->slug], 'lv-migration');
                $site->forceFill(['owner_email' => $site->owner_email ?: $user->email, 'user_id' => $site->user_id ?: $user->id])->saveQuietly();
                $accounts->send($user, 'Vos demandes de réservation arrivent maintenant via Joow', [
                    'preheader' => 'Votre calendrier et vos demandes, au même endroit.',
                    'title' => 'Votre espace réservations',
                    'intro' => Accounts::greeting($user)."\n\nLe calendrier de réservation de votre site « {$site->name} » fonctionne désormais avec Joow. "
                        ."Chaque demande vous est envoyée par e-mail, et vous pouvez aussi les retrouver, avec votre calendrier, dans votre espace.",
                    'bullets' => [
                        ['📩', 'Toutes vos demandes au même endroit', 'Dates, chambre, coordonnées du voyageur et montant estimé.'],
                        ['🗓️', 'Votre calendrier', 'Bloquez ou libérez des dates en un clic ; la synchronisation iCal se fait automatiquement.'],
                        ['🛏️', 'Vos chambres et vos tarifs', 'Prix, haute saison et nombre de nuits minimum, modifiables à tout moment.'],
                    ],
                    'rows' => array_values(array_filter([['Votre site', $site->custom_domain ? 'https://'.$site->custom_domain : 'https://'.$site->slug.'.joow.fr']])),
                    'cta' => ['label' => 'Ouvrir mes réservations', 'url' => $accounts->magicLink($user, route('reservations.index', absolute: false), 14)],
                    'note' => 'Ce lien vous connecte directement, sans mot de passe, pendant 14 jours. Une question ? Répondez simplement à cet e-mail.',
                ]);
            }
        }

        $this->info($dry ? 'Simulation terminée (rien n\'a été modifié).' : 'Site détaché de l\'ancien serveur.');

        return self::SUCCESS;
    }

    /** Mentions légales : contenu figé dans /lv-assets/legal.json (hébergeur à jour), script pointé dessus. */
    private function legalScript(string $js, string $root, string $html): string
    {
        $siteId = preg_match('#(?:LEGAL_SITE_ID|SITE_ID)\s*=\s*[\'"]([0-9a-f-]{36})#i', $html, $m) ? $m[1]
            : (preg_match('#data-site-id="([0-9a-f-]{36})"#i', $html, $m) ? $m[1] : null);
        if ($siteId) {
            try {
                $legal = Http::timeout(20)->get(self::OLD.'/api/get-legal.php', ['site_id' => $siteId])->json();
            } catch (\Throwable) {
                $legal = null;
            }
            if (is_array($legal) && ! empty($legal['success'])) {
                $legal['data']['host_name'] = 'Hostinger International Ltd (serveur Joow)';
                $legal['data']['host_address'] = '61 Lordou Vironos Street, 6023 Larnaca, Chypre';
                @mkdir($root.'/lv-assets', 0777, true);
                file_put_contents($root.'/lv-assets/legal.json', json_encode($legal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return str_replace(self::OLD.'/api/get-legal.php', '/lv-assets/legal.json', $js);
    }
}
