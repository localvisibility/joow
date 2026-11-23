<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteStat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Bascule l'historique statistique d'un ancien site Local Visibility dans Joow (tableau site_stats) :
 *  --bookings : demandes de réservation par jour, reconstituées depuis le fichier de l'ancien calendrier ;
 *  --ga       : visites par jour, depuis un export CSV de Google Analytics 4 (dimension Date + Vues/Sessions/Utilisateurs).
 * Rejouable sans doublon : pour chaque jour, on garde la plus grande valeur entre l'existant et l'import.
 */
class ImportLvStats extends Command
{
    protected $signature = 'lv:stats {lv_slug : identifiant Local Visibility du site} {--site= : slug du site dans Joow (par défaut : le même)}
        {--bookings= : fichier ou dossier JSON des demandes de l\'ancien calendrier} {--ga= : export CSV de Google Analytics (Date + Vues)} {--dry-run}';

    protected $description = 'Importe l\'historique des visites (Google Analytics) et des demandes d\'un ancien site Local Visibility';

    public function handle(): int
    {
        $lv = (string) $this->argument('lv_slug');
        $site = Site::where('slug', $this->option('site') ?: $lv)->first();
        if (! $site) {
            $this->error('Site Joow introuvable : précisez --site=<slug>.');

            return self::FAILURE;
        }
        if (! $this->option('bookings') && ! $this->option('ga')) {
            $this->error('Indiquez --bookings=<fichier> et/ou --ga=<export.csv>.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        if ($path = $this->option('bookings')) {
            $perDay = [];
            foreach (ImportLvReservations::read((string) $path) as $r) {
                if (($s = $r['slug'] ?? $r['site_slug'] ?? null) && $s !== $lv) {
                    continue;
                }
                $c = $r['created_at'] ?? $r['createdAt'] ?? $r['submitted_at'] ?? $r['timestamp'] ?? $r['date'] ?? null;
                try {
                    $day = $c ? (is_numeric($c) ? Carbon::createFromTimestamp((int) ($c > 1e12 ? $c / 1000 : $c)) : Carbon::parse($c))->toDateString() : null;
                } catch (\Throwable) {
                    $day = null;
                }
                if ($day && $day < now()->toDateString()) {
                    $perDay[$day] = ($perDay[$day] ?? 0) + 1;
                }
            }
            $this->report('Demandes de réservation', $this->store($site->slug, 'bookings', $perDay, $dry), array_sum($perDay));
        }

        if ($csv = $this->option('ga')) {
            $perDay = $this->readGa((string) $csv);
            if (! $perDay) {
                $this->error('Export Google Analytics illisible : il faut une colonne Date (ou « Nième jour ») et une colonne Vues, Sessions ou Utilisateurs.');

                return self::FAILURE;
            }
            $this->report('Visites (Google Analytics)', $this->store($site->slug, 'views', $perDay, $dry), array_sum($perDay));
        }

        $this->info($dry ? 'Simulation terminée (rien n\'a été modifié).' : 'Historique importé : il apparaît dans Statistiques (période « 1 an »).');

        return self::SUCCESS;
    }

    /** Écrit les valeurs jour par jour (max entre l'existant et l'import) ; renvoie [nb jours, premier, dernier]. */
    private function store(string $slug, string $col, array $perDay, bool $dry): array
    {
        ksort($perDay);
        if (! $dry) {
            foreach ($perDay as $day => $n) {
                $row = SiteStat::where('site_slug', $slug)->whereDate('day', $day)->first() ?? SiteStat::create(['site_slug' => $slug, 'day' => $day]);
                if ((int) $row->{$col} < $n) {
                    $row->forceFill([$col => $n])->save();
                }
            }
        }

        return [count($perDay), array_key_first($perDay), array_key_last($perDay)];
    }

    private function report(string $label, array $r, int $total): void
    {
        [$days, $from, $to] = $r;
        $this->line($days ? "  $label : $total sur $days jour(s), du ".Carbon::parse($from)->format('d/m/Y').' au '.Carbon::parse($to)->format('d/m/Y') : "  $label : rien à importer");
    }

    /** Export CSV de GA4 : lignes « # … » de commentaires, puis un tableau Date|Nième jour + métrique. */
    private function readGa(string $file): array
    {
        $lines = preg_split('/\r\n|\r|\n/', ltrim((string) @file_get_contents($file), "\xEF\xBB\xBF"));
        $start = null;
        $header = null;
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^#\s*(?:Start date|Date de début)\s*:\s*(\d{8})/i', $line, $m)) {
                $start ??= Carbon::createFromFormat('Ymd', $m[1])->startOfDay();
            }
            if (trim($line) === '' || str_starts_with($line, '#')) {
                if ($header && $out) {
                    break; // fin du premier tableau utile
                }
                $header = null;

                continue;
            }
            $sep = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            $cells = array_map('trim', str_getcsv($line, $sep));
            if (! $header) {
                $norm = array_map(fn ($h) => mb_strtolower($h), $cells);
                $d = $this->col($norm, ['date', 'jour', 'nth day', 'nième jour', 'n-ième jour', 'day']);
                $v = $this->col($norm, ['views', 'vues', 'screen page views', 'pages vues', 'sessions', 'users', 'utilisateurs', 'active users', 'utilisateurs actifs', 'total users']);
                $header = $d !== null && $v !== null ? [$d, $v, str_contains($norm[$d], 'nth') || str_contains($norm[$d], 'ième')] : null;

                continue;
            }
            [$d, $v, $nth] = $header;
            $raw = $cells[$d] ?? '';
            $n = (int) preg_replace('/[^\d]/', '', (string) ($cells[$v] ?? ''));
            try {
                $day = match (true) {
                    $nth => $start ? $start->copy()->addDays((int) $raw) : null,
                    (bool) preg_match('/^\d{8}$/', $raw) => Carbon::createFromFormat('Ymd', $raw),
                    (bool) preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $raw) => Carbon::createFromFormat('d/m/Y', $raw),
                    default => Carbon::parse($raw),
                };
            } catch (\Throwable) {
                $day = null;
            }
            if ($day && $n > 0 && $day->toDateString() < now()->toDateString()) {
                $k = $day->toDateString();
                $out[$k] = ($out[$k] ?? 0) + $n;
            }
        }

        return $out;
    }

    /** Index de la première colonne dont l'en-tête correspond (ordre de préférence des noms). */
    private function col(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $i = array_search($name, $headers, true);
            if ($i !== false) {
                return $i;
            }
        }

        return null;
    }
}
