<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Services\Accounts;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Récupère les demandes de réservation enregistrées par l'ancien calendrier Local Visibility
 * (fichiers JSON copiés depuis l'hébergement OVH) : elles arrivent dans Joow → Réservations,
 * et le propriétaire reçoit par e-mail le récapitulatif des demandes qu'il n'avait pas reçues.
 *
 * Accepte un fichier ou un dossier de .json ; chaque fichier peut contenir une demande, une liste,
 * ou un objet {reservations|bookings|data: [...]}. Champs reconnus : ceux envoyés par l'ancien widget
 * (checkin, checkout, guests, guest_name, guest_email, guest_phone, guest_message, unit_id, slug) et leurs variantes.
 */
class ImportLvReservations extends Command
{
    protected $signature = 'lv:reservations {path : fichier ou dossier JSON copié depuis OVH} {lv_slug : identifiant Local Visibility du site}
        {--site= : slug du site dans Joow (par défaut : le même)} {--since= : ne garder que les demandes reçues depuis cette date (AAAA-MM-JJ)}
        {--notify : envoyer au propriétaire le récapitulatif des demandes récupérées} {--dry-run}';

    protected $description = 'Récupère les demandes de réservation de l\'ancien calendrier Local Visibility';

    public function handle(Accounts $accounts): int
    {
        $lv = (string) $this->argument('lv_slug');
        $site = Site::where('slug', $this->option('site') ?: $lv)->first();
        if (! $site) {
            $this->error('Site Joow introuvable : précisez --site=<slug>.');

            return self::FAILURE;
        }
        $rows = self::read((string) $this->argument('path'));
        if (! $rows) {
            $this->error('Aucune demande lisible dans ce fichier ou ce dossier.');

            return self::FAILURE;
        }

        // Correspondance des chambres : identifiants de l'ancien calendrier → chambres Joow (même ordre)
        $rooms = Room::where('site_id', $site->id)->orderBy('sort_order')->get()->values();
        $unitMap = [];
        try {
            $units = (array) (Http::timeout(15)->get(ImportLvBooking::API, ['action' => 'get_config', 'slug' => $lv])->json('config.units') ?? []);
            foreach (array_values($units) as $i => $u) {
                if (isset($rooms[$i])) {
                    $unitMap[(string) ($u['id'] ?? '')] = $rooms[$i];
                }
            }
        } catch (\Throwable) {
            // sans l'ancienne API : la chambre reste non attribuée
        }

        $since = $this->option('since') ? Carbon::parse($this->option('since'))->startOfDay() : null;
        $dry = (bool) $this->option('dry-run');
        $imported = [];
        $skipped = 0;
        foreach ($rows as $r) {
            $get = fn (array $keys) => collect($keys)->map(fn ($k) => data_get($r, $k))->first(fn ($v) => $v !== null && $v !== '');
            if (($s = $get(['slug', 'site_slug', 'site'])) && $s !== $lv) {
                continue;
            }
            $in = $get(['checkin', 'check_in', 'arrival', 'start', 'date_start']);
            $out = $get(['checkout', 'check_out', 'departure', 'end', 'date_end']);
            $name = $get(['guest_name', 'name', 'nom', 'client_name']);
            if (! $in || ! $out || ! $name) {
                $skipped++;

                continue;
            }
            try {
                $in = Carbon::parse($in)->toDateString();
                $out = Carbon::parse($out)->toDateString();
            } catch (\Throwable) {
                $skipped++;

                continue;
            }
            $created = $get(['created_at', 'createdAt', 'date', 'timestamp', 'submitted_at']);
            $created = $created ? (is_numeric($created) ? Carbon::createFromTimestamp((int) ($created > 1e12 ? $created / 1000 : $created)) : Carbon::parse($created)) : null;
            if ($since && $created && $created->lt($since)) {
                continue;
            }
            $email = $get(['guest_email', 'email']);
            $room = $unitMap[(string) $get(['unit_id', 'unit', 'room_id'])] ?? null;
            // Déjà importée ? (mêmes dates, même voyageur)
            $exists = RoomBooking::where('site_slug', $site->slug)->whereDate('check_in', $in)->whereDate('check_out', $out)->where('name', $name)->exists();
            if ($exists) {
                $skipped++;

                continue;
            }
            $row = [
                'site_id' => $site->id, 'site_slug' => $site->slug, 'room_id' => $room?->id,
                'check_in' => $in, 'check_out' => $out, 'guests' => max(1, (int) ($get(['guests', 'nb_guests', 'persons']) ?? 2)),
                'nights' => Carbon::parse($in)->diffInDays(Carbon::parse($out)),
                'name' => mb_substr((string) $name, 0, 120), 'phone' => mb_substr((string) ($get(['guest_phone', 'phone', 'telephone']) ?? ''), 0, 40), 'email' => $email ? mb_substr((string) $email, 0, 160) : null,
                'notes' => trim('Demande reçue sur l\'ancien calendrier'.($created ? ' le '.$created->format('d/m/Y à H\hi') : '').'. '.(string) ($get(['guest_message', 'message', 'notes']) ?? '')),
                'status' => 'pending', 'source' => 'ancien-calendrier',
            ];
            $imported[] = $row + ['room_name' => $room?->name, 'received' => $created];
            if (! $dry) {
                RoomBooking::create($row);
            }
        }

        $this->info(count($imported).' demande(s) récupérée(s)'.($skipped ? " · $skipped ignorée(s) (déjà importées ou incomplètes)" : ''));
        foreach ($imported as $b) {
            $this->line('  '.($b['received'] ? $b['received']->format('d/m') : '--/--')." · {$b['name']} · du ".Carbon::parse($b['check_in'])->format('d/m/Y').' au '.Carbon::parse($b['check_out'])->format('d/m/Y')." ({$b['nights']} nuit(s))".($b['room_name'] ? " · {$b['room_name']}" : '').' · '.($b['email'] ?: $b['phone']));
        }

        if ($imported && $this->option('notify') && ! $dry) {
            $to = $site->owner_email ?: ($site->module('rooms')['notify_email'] ?? null);
            if ($to) {
                [$user] = $accounts->ensure($to, [$site->slug], 'lv-migration');
                $nights = array_sum(array_column($imported, 'nights'));
                $accounts->send($user, count($imported).' demande(s) de réservation à recontacter', [
                    'preheader' => 'Les demandes reçues par votre ancien calendrier, qui ne vous avaient pas été transmises.',
                    'title' => 'Vos demandes non reçues',
                    'intro' => Accounts::greeting($user)."\n\nSuite à un incident sur l'ancien calendrier, ces demandes de réservation ne vous ont pas été transmises par e-mail. "
                        ."Elles sont maintenant dans votre espace, avec les coordonnées des voyageurs : nous vous conseillons de les recontacter rapidement.",
                    'rows' => array_map(fn ($b) => [
                        $b['name'].($b['room_name'] ? ' · '.$b['room_name'] : ''),
                        Carbon::parse($b['check_in'])->format('d/m').' → '.Carbon::parse($b['check_out'])->format('d/m/Y').' · '.trim(($b['email'] ?? '').' '.($b['phone'] ?? '')),
                    ], array_slice($imported, 0, 40)),
                    'cta' => ['label' => 'Voir mes demandes ('.$nights.' nuitées)', 'url' => $accounts->magicLink($user, route('reservations.index', absolute: false), 14)],
                    'note' => 'Les nouvelles demandes vous arrivent désormais normalement par e-mail. Une question ? Répondez simplement à cet e-mail.',
                ]);
                $this->line("  Récapitulatif envoyé à $to");
            }
        }

        return self::SUCCESS;
    }

    /** Lit un fichier ou un dossier de JSON et renvoie la liste des demandes. */
    public static function read(string $path): array
    {
        $files = is_dir($path) ? array_merge(glob(rtrim($path, '/').'/*.json') ?: [], glob(rtrim($path, '/').'/**/*.json') ?: []) : [$path];
        $out = [];
        foreach (array_unique($files) as $f) {
            $d = json_decode((string) @file_get_contents($f), true);
            if (! is_array($d)) {
                continue;
            }
            $list = $d['reservations'] ?? $d['bookings'] ?? $d['requests'] ?? $d['data'] ?? $d;
            if (is_array($list) && array_is_list($list)) {
                $out = array_merge($out, array_filter($list, 'is_array'));
            } elseif (is_array($list)) {
                // objet indexé par identifiant, ou une seule demande
                $vals = array_values($list);
                $out = array_merge($out, ($vals && is_array($vals[0]) && ! isset($list['checkin'])) ? array_filter($vals, 'is_array') : [$list]);
            }
        }

        return $out;
    }
}
