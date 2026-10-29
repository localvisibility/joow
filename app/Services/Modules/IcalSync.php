<?php

namespace App\Services\Modules;

use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importe les indisponibilités d'un flux iCal (Airbnb, Booking, Abritel, Google…)
 * dans room_blocks, pour éviter les doubles réservations.
 */
class IcalSync
{
    public function syncRoom(Room $room): int
    {
        $feeds = $room->icalFeeds();
        $prefixes = [];
        $count = 0;

        foreach ($feeds as $feed) {
            $prefix = substr(md5($feed['url']), 0, 8).':';
            $prefixes[] = $prefix;
            try {
                $ics = Http::timeout(20)->get($feed['url'])->throw()->body();
            } catch (\Throwable $e) {
                Log::warning("iCal {$room->id} ({$feed['label']}) : {$e->getMessage()}");

                continue; // flux injoignable : on garde ses blocages actuels
            }

            $uids = [];
            foreach ($this->parse($ics) as $ev) {
                if (str_ends_with($ev['uid'], '@joow.fr')) {
                    continue; // nos propres réservations renvoyées par la plateforme : pas de boucle
                }
                $uid = $prefix.$ev['uid'];
                $uids[] = $uid;
                RoomBlock::updateOrCreate(
                    ['room_id' => $room->id, 'external_uid' => $uid],
                    ['site_slug' => $room->site_slug, 'start' => $ev['start'], 'end' => $ev['end'], 'source' => 'ical', 'summary' => trim(($feed['label'] !== 'iCal' ? $feed['label'].' · ' : '').($ev['summary'] ?? ''), ' ·') ?: null]
                );
                $count++;
            }
            // Événements disparus de ce flux
            RoomBlock::where('room_id', $room->id)->where('source', 'ical')->where('external_uid', 'like', $prefix.'%')
                ->when($uids, fn ($q) => $q->whereNotIn('external_uid', $uids))->delete();
        }

        // Flux retirés de la chambre (et anciens blocages sans préfixe)
        RoomBlock::where('room_id', $room->id)->where('source', 'ical')->get(['id', 'external_uid'])
            ->filter(fn ($b) => ! in_array(substr((string) $b->external_uid, 0, 9), $prefixes, true))
            ->each->delete();

        $room->forceFill(['ical_synced_at' => now()])->save();

        return $count;
    }

    /** Flux iCal exporté : réservations Joow (en attente et confirmées) et blocages manuels. */
    public function export(Room $room): string
    {
        $esc = fn ($v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], (string) $v);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Joow//Chambres//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:'.$esc($room->name)];
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $add = function (string $uid, $start, $end, string $summary) use (&$lines, $stamp, $esc) {
            array_push($lines, 'BEGIN:VEVENT', 'UID:'.$uid, 'DTSTAMP:'.$stamp,
                'DTSTART;VALUE=DATE:'.Carbon::parse($start)->format('Ymd'), 'DTEND;VALUE=DATE:'.Carbon::parse($end)->format('Ymd'),
                'SUMMARY:'.$esc($summary), 'END:VEVENT');
        };
        $since = now()->subDays(30)->toDateString();
        foreach ($room->bookings()->whereIn('status', ['pending', 'confirmed'])->where('check_out', '>=', $since)->get() as $b) {
            $add('booking-'.$b->id.'@joow.fr', $b->check_in, $b->check_out, $b->status === 'confirmed' ? 'Réservé' : 'En attente');
        }
        foreach (RoomBlock::where('site_slug', $room->site_slug)->where('source', 'manual')->where(fn ($q) => $q->where('room_id', $room->id)->orWhereNull('room_id'))->where('end', '>=', $since)->get() as $b) {
            $add('block-'.$b->id.'@joow.fr', $b->start, $b->end, 'Indisponible');
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /** @return array<int, array{uid:string,start:string,end:string,summary:?string}> */
    public function parse(string $ics): array
    {
        // Déplie les lignes (RFC 5545) et normalise les fins de ligne
        $ics = preg_replace("/\r\n[ \t]/", '', str_replace("\r\n", "\n", $ics));
        $out = [];
        if (! preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $m)) {
            return $out;
        }
        foreach ($m[1] as $block) {
            $get = function (string $prop) use ($block) {
                return preg_match('/^'.$prop.'[^:]*:(.+)$/mi', $block, $mm) ? trim($mm[1]) : null;
            };
            $start = $get('DTSTART');
            $end = $get('DTEND');
            if (! $start) {
                continue;
            }
            try {
                $s = $this->toDate($start);
                $e = $end ? $this->toDate($end) : $s->copy()->addDay();
            } catch (\Throwable) {
                continue;
            }
            $out[] = [
                'uid'     => $get('UID') ?: md5($start.($end ?? '')),
                'start'   => $s->toDateString(),
                'end'     => $e->toDateString(),
                'summary' => $get('SUMMARY') ? mb_substr($get('SUMMARY'), 0, 120) : null,
            ];
        }

        return $out;
    }

    private function toDate(string $v): Carbon
    {
        $v = trim($v);
        if (preg_match('/^\d{8}$/', $v)) {
            return Carbon::createFromFormat('Ymd', $v)->startOfDay();
        }

        return Carbon::parse($v)->startOfDay();
    }
}
