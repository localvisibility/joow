<?php

namespace App\Services\Modules;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Import de réservations depuis un fichier Excel (.xlsx) ou CSV : export Bed-Booking, Airbnb, Booking,
 * ou simple tableau tenu par le propriétaire. Les colonnes sont reconnues par leur intitulé
 * (Arrivée, Départ, Nuits, Nom, Chambre, Téléphone, E-mail, Personnes, Montant, Statut…).
 * Les réservations importées sont confirmées : elles ferment leurs dates sur le site.
 */
class StayImport
{
    public const MAX_ROWS = 3000;

    private const ALIASES = [
        'check_in' => ['arrivee', 'date d arrivee', 'date arrivee', 'check in', 'checkin', 'debut', 'date de debut', 'date debut', 'du', 'arrival', 'arrival date', 'start', 'start date', 'from', 'entree', 'date entree'],
        'check_out' => ['depart', 'date de depart', 'date depart', 'check out', 'checkout', 'fin', 'date de fin', 'date fin', 'au', 'departure', 'departure date', 'end', 'end date', 'to', 'sortie', 'date sortie'],
        'nights' => ['nuits', 'nuitees', 'nombre de nuits', 'nb nuits', 'nights', 'number of nights', 'duree'],
        'name' => ['nom', 'client', 'nom du client', 'nom client', 'voyageur', 'guest', 'guest name', 'name', 'nom complet', 'full name', 'reserve par', 'booker', 'locataire', 'hote', 'titulaire'],
        'first_name' => ['prenom', 'first name', 'firstname'],
        'last_name' => ['nom de famille', 'last name', 'lastname', 'surname'],
        'room' => ['chambre', 'chambres', 'logement', 'hebergement', 'room', 'rooms', 'unit', 'unite', 'propriete', 'bien', 'appartement', 'gite', 'listing', 'annonce', 'type de chambre', 'room type', 'accommodation'],
        'phone' => ['telephone', 'tel', 'phone', 'mobile', 'portable', 'numero de telephone', 'phone number'],
        'email' => ['email', 'e mail', 'mail', 'courriel', 'adresse e mail', 'adresse email'],
        'guests' => ['personnes', 'nb personnes', 'nombre de personnes', 'voyageurs', 'nb voyageurs', 'adultes', 'guests', 'persons', 'pax', 'occupants'],
        'total' => ['total', 'montant', 'montant total', 'prix', 'prix total', 'price', 'total price', 'amount', 'tarif', 'revenu', 'payout', 'total ttc'],
        'notes' => ['notes', 'note', 'commentaire', 'commentaires', 'remarques', 'remarque', 'message', 'observations', 'informations', 'source', 'canal', 'channel', 'origine', 'reference', 'ref', 'numero de reservation'],
        'status' => ['statut', 'status', 'etat', 'state'],
    ];

    /** Lit le fichier : liste de lignes [colonne normalisée => valeur]. */
    public function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $table = $ext === 'xlsx' ? $this->xlsx($path) : $this->csv($path);
        // Ligne d'en-tête : la première qui contient une date d'arrivée reconnue
        foreach (array_slice($table, 0, 15, true) as $i => $row) {
            $map = $this->mapHeader($row);
            if (isset($map['check_in']) && (isset($map['check_out']) || isset($map['nights']))) {
                $out = [];
                foreach (array_slice($table, $i + 1, self::MAX_ROWS) as $r) {
                    if (! array_filter($r, fn ($v) => trim((string) $v) !== '')) {
                        continue;
                    }
                    $row = [];
                    foreach ($map as $key => $col) {
                        $row[$key] = trim((string) ($r[$col] ?? ''));
                    }
                    $out[] = $row;
                }

                return $out;
            }
        }
        throw new \RuntimeException('Colonnes introuvables : le fichier doit avoir au moins une colonne « Arrivée » et une colonne « Départ » (ou « Nuits »).');
    }

    /** Analyse les lignes : réservations prêtes, doublons, lignes à corriger. */
    public function analyse(Site $site, array $rows, ?string $defaultRoomId = null): array
    {
        $rooms = Room::where('site_id', $site->id)->orderBy('sort_order')->get();
        $default = $defaultRoomId ? $rooms->firstWhere('id', $defaultRoomId) : null;
        $today = now()->toDateString();
        $items = [];
        $seen = [];
        foreach ($rows as $n => $r) {
            $item = ['line' => $n + 2, 'raw_room' => $r['room'] ?? ''];
            $in = $this->date($r['check_in'] ?? '');
            $out = $this->date($r['check_out'] ?? '');
            if ($in && ! $out && ($nights = (int) preg_replace('/\D/', '', (string) ($r['nights'] ?? ''))) > 0) {
                $out = Carbon::parse($in)->addDays($nights)->toDateString();
            }
            $name = trim(($r['name'] ?? '') ?: trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')));
            if (($r['first_name'] ?? '') !== '' && ($r['name'] ?? '') !== '' && ! str_contains(mb_strtolower($r['name']), mb_strtolower($r['first_name']))) {
                $name = trim($r['first_name'].' '.$r['name']);
            }
            $room = $this->room($rooms, (string) ($r['room'] ?? '')) ?? ($rooms->count() === 1 ? $rooms->first() : $default);
            $item += [
                'check_in' => $in, 'check_out' => $out, 'name' => Str::limit($name ?: 'Réservation', 120, ''),
                'room_id' => $room?->id, 'room' => $room?->name,
                'phone' => Str::limit((string) ($r['phone'] ?? ''), 40, ''), 'email' => filter_var($r['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $r['email'] : null,
                'guests' => max(1, min(30, (int) preg_replace('/\D/', '', (string) ($r['guests'] ?? '')) ?: 2)),
                'total' => $this->money($r['total'] ?? ''), 'notes' => Str::limit((string) ($r['notes'] ?? ''), 900, ''),
            ];
            $status = $this->norm((string) ($r['status'] ?? ''));
            $item['state'] = match (true) {
                ! $in || ! $out || $out <= $in => 'invalid',
                (bool) preg_match('/annul|cancel|refus|declin|rejet|no show/', $status) => 'cancelled',
                $out < $today => 'past',
                ! $room => 'no_room',
                default => 'ok',
            };
            if ($item['state'] === 'invalid') {
                $item['reason'] = 'dates illisibles';
            }
            if ($item['state'] === 'ok') {
                $key = $room->id.'|'.$in.'|'.$out;
                $existing = RoomBooking::where('room_id', $room->id)->whereDate('check_in', $in)->whereDate('check_out', $out)->where('status', '!=', 'cancelled')->first();
                if (isset($seen[$key]) || ($existing && $existing->status !== 'archived')) {
                    $item['state'] = 'duplicate';
                } elseif ($existing) {
                    $item['state'] = 'reactivate'; // séjour de l'historique : il redevient une réservation confirmée
                    $item['existing_id'] = $existing->id;
                }
                $seen[$key] = true;
            }
            $items[] = $item;
        }
        $count = fn ($s) => count(array_filter($items, fn ($i) => in_array($i['state'], (array) $s, true)));

        return [
            'items' => $items,
            'summary' => ['ok' => $count(['ok', 'reactivate']), 'duplicate' => $count('duplicate'), 'past' => $count('past'), 'cancelled' => $count('cancelled'), 'no_room' => $count('no_room'), 'invalid' => $count('invalid')],
            'rooms_found' => array_values(array_unique(array_filter(array_map(fn ($i) => $i['raw_room'], $items)))),
        ];
    }

    /** Enregistre les réservations prêtes ; renvoie le nombre importé. */
    public function import(Site $site, array $analysis): int
    {
        $n = 0;
        foreach ($analysis['items'] as $i) {
            if ($i['state'] === 'reactivate') {
                RoomBooking::whereKey($i['existing_id'])->update(['status' => 'confirmed']);
                $n++;
            } elseif ($i['state'] === 'ok') {
                RoomBooking::create([
                    'site_id' => $site->id, 'site_slug' => $site->slug, 'room_id' => $i['room_id'],
                    'check_in' => $i['check_in'], 'check_out' => $i['check_out'], 'nights' => Carbon::parse($i['check_in'])->diffInDays(Carbon::parse($i['check_out'])),
                    'guests' => $i['guests'], 'total' => $i['total'], 'name' => $i['name'], 'phone' => $i['phone'], 'email' => $i['email'],
                    'notes' => trim('Importée depuis un fichier. '.$i['notes']), 'status' => 'confirmed', 'source' => 'import',
                ]);
                $n++;
            }
        }

        return $n;
    }

    // ───────────── lecture des fichiers ─────────────

    private function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        } elseif (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($raw, "\n") ?: '';
        $sep = collect([';', ',', "\t"])->sortByDesc(fn ($s) => substr_count($first, $s))->first();
        $rows = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        while (($r = fgetcsv($h, 0, $sep, '"', '\\')) !== false && count($rows) < self::MAX_ROWS + 15) {
            $rows[] = $r;
        }
        fclose($h);

        return $rows;
    }

    /** Lecture minimale d'un .xlsx (première feuille), sans dépendance. */
    private function xlsx(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Fichier Excel illisible.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si as $si) {
                $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            foreach (range(0, $zip->numFiles - 1) as $k) {
                if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $zip->getNameIndex($k))) {
                    $sheet = $zip->getFromIndex($k);
                    break;
                }
            }
        }
        $zip->close();
        if (! $sheet) {
            throw new \RuntimeException('Aucune feuille dans ce fichier Excel.');
        }
        $sx = simplexml_load_string($sheet);
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $r = [];
            foreach ($row->c as $c) {
                $col = $this->colIndex((string) $c['r']);
                $t = (string) $c['t'];
                $v = $t === 's' ? ($strings[(int) $c->v] ?? '') : ($t === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
                $r[$col] = $v;
            }
            if ($r) {
                $max = max(array_keys($r));
                $rows[] = array_map(fn ($i) => $r[$i] ?? '', range(0, $max));
            }
            if (count($rows) >= self::MAX_ROWS + 15) {
                break;
            }
        }

        return $rows;
    }

    private function colIndex(string $ref): int
    {
        $letters = preg_replace('/\d/', '', $ref);
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    // ───────────── reconnaissance ─────────────

    private function norm(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($s))));
    }

    private function mapHeader(array $row): array
    {
        $map = [];
        $cells = array_map(fn ($c) => $this->norm((string) $c), $row);
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $a) {
                $i = array_search($a, $cells, true);
                if ($i !== false && ! in_array($i, $map, true)) {
                    $map[$key] = $i;
                    break;
                }
            }
        }
        return $map;
    }

    private function room($rooms, string $label): ?Room
    {
        $l = $this->norm($label);
        if ($l === '') {
            return null;
        }
        $strip = fn ($s) => trim(preg_replace('/^(la|le|les|l|chambre|suite|gite|appartement)\s+/', '', $s));

        return $rooms->first(fn ($r) => $this->norm($r->name) === $l)
            ?? $rooms->first(function ($r) use ($l, $strip) {
                $n = $strip($this->norm($r->name));

                return $n !== '' && (str_contains($l, $n) || str_contains($n, $strip($l)));
            });
    }

    public function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString(); // date Excel
        }
        $v = preg_replace('/\s+\d{1,2}[:h]\d{2}(:\d{2})?.*$/i', '', $v); // heure éventuelle
        foreach (['d/m/Y', 'd/m/y', 'd.m.Y', 'd.m.y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, $v);
            $err = \DateTime::getLastErrors();
            if ($d && (! $err || (! $err['warning_count'] && ! $err['error_count']))) {
                return $d->format('Y-m-d');
            }
        }
        $fr = ['janvier' => 'january', 'février' => 'february', 'fevrier' => 'february', 'mars' => 'march', 'avril' => 'april', 'mai' => 'may', 'juin' => 'june', 'juillet' => 'july', 'août' => 'august', 'aout' => 'august', 'septembre' => 'september', 'octobre' => 'october', 'novembre' => 'november', 'décembre' => 'december', 'decembre' => 'december',
            'janv.' => 'jan', 'févr.' => 'feb', 'avr.' => 'apr', 'juil.' => 'jul', 'sept.' => 'sep', 'oct.' => 'oct', 'nov.' => 'nov', 'déc.' => 'dec'];
        $en = preg_replace('/^(lun|mar|mer|jeu|ven|sam|dim)[a-z.]*\s+/iu', '', mb_strtolower($v));
        $en = strtr($en, $fr);
        try {
            return Carbon::parse($en)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(string $v): ?float
    {
        $v = preg_replace('/[^\d,.\-]/', '', $v);
        if ($v === '' || $v === '-') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? round((float) $v, 2) : null;
    }
}
