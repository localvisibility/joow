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
        if (! $room->ical_url) {
            return 0;
        }

        try {
            $ics = Http::timeout(20)->get($room->ical_url)->throw()->body();
        } catch (\Throwable $e) {
            Log::warning("iCal {$room->id} : {$e->getMessage()}");

            return 0;
        }

        $events = $this->parse($ics);
        $uids = [];

        foreach ($events as $ev) {
            $uids[] = $ev['uid'];
            RoomBlock::updateOrCreate(
                ['room_id' => $room->id, 'external_uid' => $ev['uid']],
                ['site_slug' => $room->site_slug, 'start' => $ev['start'], 'end' => $ev['end'], 'source' => 'ical', 'summary' => $ev['summary']]
            );
        }

        // Supprime les blocages iCal disparus du flux
        RoomBlock::where('room_id', $room->id)->where('source', 'ical')
            ->when($uids, fn ($q) => $q->whereNotIn('external_uid', $uids), fn ($q) => $q)
            ->delete();

        $room->forceFill(['ical_synced_at' => now()])->save();

        return count($events);
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
