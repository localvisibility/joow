<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Site;
use App\Services\Modules\IcalSync;
use App\Services\SiteRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Passe la réservation d'un ancien site Local Visibility sur Joow :
 * importe chambres, tarifs, haute saison, nuits min/max, flux iCal, dates bloquées et e-mail de notification
 * depuis l'API de l'ancien serveur, active le module Chambres, puis remplace dans la page le calendrier
 * de l'ancien serveur par le module de réservation Joow (les demandes sont envoyées par Joow).
 */
class ImportLvBooking extends Command
{
    protected $signature = 'lv:booking {lv_slug : identifiant du site chez Local Visibility (ex. clos-du-petit-jesus-cassis)}
        {--site= : slug du site dans Joow (sinon : même slug ou domaine)} {--domain= : nom de domaine du site} {--dry-run}';

    protected $description = 'Importe la réservation d\'un ancien site Local Visibility dans Joow et branche le module Joow sur la page';

    public const API = 'https://local-visibility.com/api/calendar-manager.php';

    public function handle(SiteRenderer $renderer, IcalSync $ical): int
    {
        $lv = (string) $this->argument('lv_slug');
        $dry = (bool) $this->option('dry-run');

        $res = Http::timeout(20)->get(self::API, ['action' => 'get_config', 'slug' => $lv]);
        $cfg = $res->json('config');
        if (! $res->successful() || ! is_array($cfg) || empty($cfg['units'])) {
            $this->error('Configuration introuvable sur l\'ancien serveur pour « '.$lv.' ».');

            return self::FAILURE;
        }

        $site = $this->findSite($lv);
        if (! $site) {
            $this->error('Site Joow introuvable : précisez --site=<slug> ou --domain=<domaine>.');

            return self::FAILURE;
        }
        $this->info("Site Joow : {$site->name} ({$site->slug})".($site->custom_domain ? " · {$site->custom_domain}" : ''));

        // 1) Chambres
        $rooms = [];
        foreach (array_values($cfg['units']) as $i => $u) {
            $name = trim(preg_replace('/\s+(dès|des|à partir de)\s*$/iu', '', (string) ($u['name'] ?? 'Chambre '.($i + 1))));
            $base = (float) ($u['price'] ?? $cfg['settings']['base_price'] ?? 0) ?: null;
            $weekend = (float) ($u['weekend_price'] ?? 0);
            $high = (float) ($u['high_season_price'] ?? 0);
            $season = $this->season((array) ($u['high_season_periods'] ?? $cfg['settings']['high_season_periods'] ?? []));
            $feeds = collect((array) ($u['ical_urls'] ?? []))->filter(fn ($url) => is_string($url) && str_starts_with($url, 'http'))
                ->map(fn ($url, $label) => ['label' => ucfirst((string) $label), 'url' => $url])->values()->all();
            $attrs = [
                'site_id' => $site->id, 'site_slug' => $site->slug, 'name' => $name, 'capacity' => max(1, (int) ($u['capacity'] ?? 2)),
                'price_night' => $base, 'weekend_price' => $weekend && $weekend != $base ? $weekend : null,
                'min_nights' => max(1, (int) ($u['min_nights'] ?? $cfg['settings']['min_nights'] ?? 1)), 'max_nights' => (int) ($u['max_nights'] ?? $cfg['settings']['max_nights'] ?? 30) ?: null,
                'seasons' => $season && $high > 0 ? [['name' => 'Haute saison', 'from' => $season[0], 'to' => $season[1], 'price' => $high, 'weekend_price' => null, 'min_nights' => null]] : [],
                'ical_urls' => $feeds, 'active' => true, 'sort_order' => $i,
            ];
            $this->line("  Chambre « $name » : {$base} €/nuit".($attrs['seasons'] ? " · haute saison {$high} € ({$season[0]} → {$season[1]})" : '')." · {$attrs['min_nights']} nuit(s) min · ".count($feeds).' flux iCal');
            if (! $dry) {
                $room = Room::where('site_id', $site->id)->where('name', $name)->first() ?? new Room;
                $room->forceFill($attrs)->save();
                $rooms[(string) ($u['id'] ?? $i)] = $room;
            }
        }

        // 2) Dates bloquées à venir (regroupées en périodes, fin exclusive)
        $today = now()->toDateString();
        $blocks = 0;
        foreach ((array) ($cfg['blocked_dates'] ?? []) as $unitId => $dates) {
            $dates = collect((array) $dates)->filter(fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d >= $today)->unique()->sort()->values();
            foreach ($this->ranges($dates->all()) as [$start, $end]) {
                $blocks++;
                if (! $dry && isset($rooms[$unitId])) {
                    $exists = RoomBlock::where('room_id', $rooms[$unitId]->id)->whereDate('start', $start)->whereDate('end', $end)->exists();
                    if (! $exists) {
                        RoomBlock::create(['site_slug' => $site->slug, 'room_id' => $rooms[$unitId]->id, 'start' => $start, 'end' => $end, 'source' => 'manual', 'summary' => 'Importé de l\'ancien calendrier']);
                    }
                }
            }
        }
        $this->line("  $blocks période(s) bloquée(s) à venir importée(s)");

        // 3) Module Chambres : demandes envoyées par Joow au propriétaire
        $email = (string) ($cfg['notification_email'] ?? $cfg['settings']['notification_email'] ?? '');
        $this->line('  Notifications envoyées à : '.($email ?: '(aucune adresse)'));
        if (! $dry) {
            $site->setModule('rooms', array_merge($site->module('rooms'), ['enabled' => true, 'notify_email' => $email, 'instant' => false]));
            foreach ($rooms as $room) {
                if ($room->icalFeeds()) {
                    try {
                        $ical->syncRoom($room);
                    } catch (\Throwable $e) {
                        $this->warn("  Synchronisation iCal de « {$room->name} » : ".$e->getMessage());
                    }
                }
            }
        }

        // 4) Page : le calendrier de l'ancien serveur est remplacé par le module Joow
        // La page réellement servie est le fichier publié ; la copie en base peut être plus ancienne (sans le calendrier)
        $file = rtrim((string) config('services.sites_path', '/var/www/sites'), '/').'/'.$site->slug.'/index.html';
        $disk = is_file($file) ? (string) @file_get_contents($file) : '';
        $html = str_contains($disk, 'lv-calendar-widget') ? $disk : (string) $site->html_content;
        if ($disk !== '' && $html === $disk) {
            $this->line('  Page publiée : '.$file);
        }
        if (! empty($site->site_data['content'])) {
            $this->line('  Site généré par Joow : le module Chambres s\'affiche directement, la page n\'est pas modifiée.');
        } elseif (! str_contains($html, 'lv-calendar-widget')) {
            $this->warn('  Calendrier de réservation introuvable dans la page : à brancher à la main.');
        } else {
            $widget = rtrim((string) config('app.url'), '/').'/widgets/booking.js?v=1';
            $new = preg_replace('#<script\s+src="https://local-visibility\.com/widgets/calendar\.js[^"]*"\s*>\s*</script>#i', '<script src="'.$widget.'"></script>', $html);
            $new = preg_replace('#<div id="lv-calendar-widget"(?![^>]*data-joow-slug)#', '<div id="lv-calendar-widget" data-joow-slug="'.$site->slug.'"', (string) $new, 1);
            if ($new === $html || ! str_contains((string) $new, '/widgets/booking.js')) {
                $this->warn('  Script du calendrier non reconnu : page non modifiée.');
            } else {
                $this->line('  Page : calendrier remplacé par le module de réservation Joow');
                if (! $dry) {
                    $site->forceFill(['html_content' => $new])->saveQuietly();
                    $renderer->write($site->slug, (string) $new);
                }
            }
        }

        $this->info($dry ? 'Simulation terminée (rien n\'a été modifié).' : 'Réservation passée sur Joow.');

        return self::SUCCESS;
    }

    private function findSite(string $lv): ?Site
    {
        if ($s = $this->option('site')) {
            return Site::where('slug', $s)->first();
        }
        if ($d = $this->option('domain')) {
            $d = strtolower(preg_replace('#^https?://(www\.)?#', '', rtrim($d, '/')));

            return Site::whereIn('custom_domain', [$d, 'www.'.$d])->first();
        }

        return Site::where('slug', $lv)->first()
            ?? Site::where('custom_domain', 'like', '%'.Str::before($lv, '-cassis').'%')->first();
    }

    /** « 5b, 6a … 9a » (a = 1-15, b = 16-fin) → ['05-16', '09-15']. */
    private function season(array $periods): ?array
    {
        $p = collect($periods)->filter(fn ($x) => preg_match('/^(\d{1,2})([ab])$/', (string) $x))->map(function ($x) {
            preg_match('/^(\d{1,2})([ab])$/', (string) $x, $m);

            return ['m' => (int) $m[1], 'h' => $m[2]];
        })->sortBy(fn ($x) => $x['m'] * 2 + ($x['h'] === 'b' ? 1 : 0))->values();
        if ($p->isEmpty()) {
            return null;
        }
        $first = $p->first();
        $last = $p->last();
        $from = sprintf('%02d-%s', $first['m'], $first['h'] === 'a' ? '01' : '16');
        $to = $last['h'] === 'a' ? sprintf('%02d-15', $last['m']) : Carbon::create(2027, $last['m'], 1)->endOfMonth()->format('m-d');

        return [$from, $to];
    }

    /** Dates triées → périodes [début, fin exclusive]. */
    private function ranges(array $dates): array
    {
        $out = [];
        $start = $prev = null;
        foreach ($dates as $d) {
            if ($prev && Carbon::parse($prev)->addDay()->toDateString() === $d) {
                $prev = $d;

                continue;
            }
            if ($start) {
                $out[] = [$start, Carbon::parse($prev)->addDay()->toDateString()];
            }
            $start = $prev = $d;
        }
        if ($start) {
            $out[] = [$start, Carbon::parse($prev)->addDay()->toDateString()];
        }

        return $out;
    }
}
