<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Site;
use App\Services\Modules\IcalSync;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;

/** Espace client : chambres / unités, blocages et synchronisation iCal. */
class RoomsController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);
        $rooms = $site->rooms()->get()->each(fn (Room $r) => $r->setAttribute('export_url', $r->exportUrl()));
        $blocks = RoomBlock::where('site_slug', $slug)->where('end', '>=', now()->subMonth()->toDateString())->orderBy('start')->get();

        return response()->json(['rooms' => $rooms, 'blocks' => $blocks]);
    }

    public function store(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $room = Room::create($this->validated($request) + ['site_id' => $site->id, 'site_slug' => $slug, 'sort_order' => (int) $site->rooms()->max('sort_order') + 1]);
        $this->republish($site, $renderer);

        return response()->json(['room' => $room]);
    }

    public function update(Request $request, string $slug, Room $room, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        abort_unless($room->site_slug === $slug, 404);
        $room->update($this->validated($request));
        $this->republish($site, $renderer);

        return response()->json(['room' => $room->fresh()]);
    }

    public function destroy(Request $request, string $slug, Room $room, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        abort_unless($room->site_slug === $slug, 404);
        $room->delete();
        RoomBlock::where('room_id', $room->id)->delete();
        $this->republish($site, $renderer);

        return response()->json(['ok' => true]);
    }

    /** Blocage manuel d'une période (fermeture, entretien, réservation hors site). */
    public function block(Request $request, string $slug)
    {
        $this->ownedSite($request, $slug);
        $data = $request->validate([
            'room_id' => ['nullable', 'uuid'], 'start' => ['required', 'date'], 'end' => ['required', 'date', 'after:start'], 'summary' => ['nullable', 'string', 'max:120'],
        ]);
        $b = RoomBlock::create($data + ['site_slug' => $slug, 'source' => 'manual']);

        return response()->json(['block' => $b]);
    }

    public function unblock(Request $request, string $slug, RoomBlock $block)
    {
        $this->ownedSite($request, $slug);
        abort_unless($block->site_slug === $slug && $block->source === 'manual', 404);
        $block->delete();

        return response()->json(['ok' => true]);
    }

    /** Synchronisation iCal immédiate. */
    public function sync(Request $request, string $slug, IcalSync $ical)
    {
        $site = $this->ownedSite($request, $slug);
        $n = 0;
        foreach ($site->rooms()->get() as $room) {
            if ($room->icalFeeds()) {
                $n += $ical->syncRoom($room);
            }
        }

        return response()->json(['ok' => true, 'events' => $n]);
    }

    /** Flux iCal public d'une chambre (protégé par jeton). */
    public function export(Room $room, string $token, IcalSync $ical)
    {
        abort_unless($room->ical_token && hash_equals($room->ical_token, $token), 404);

        return response($ical->export($room), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.\Illuminate\Support\Str::slug($room->name).'.ics"',
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'], 'price_night' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array', 'max:8'], 'photos.*' => ['url'], 'amenities' => ['nullable', 'array', 'max:20'], 'amenities.*' => ['string', 'max:40'],
            'ical_url' => ['nullable', 'url', 'max:500'], 'active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer'],
            'weekend_price' => ['nullable', 'numeric', 'min:0'], 'min_nights' => ['nullable', 'integer', 'min:1', 'max:60'], 'max_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'], 'included_guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'extra_guest_fee' => ['nullable', 'numeric', 'min:0'], 'tourist_tax' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'seasons' => ['nullable', 'array', 'max:12'],
            'seasons.*.name' => ['nullable', 'string', 'max:40'],
            'seasons.*.from' => ['required', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'seasons.*.to' => ['required', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'seasons.*.price' => ['nullable', 'numeric', 'min:0'], 'seasons.*.weekend_price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.min_nights' => ['nullable', 'integer', 'min:1', 'max:60'],
            'ical_urls' => ['nullable', 'array', 'max:6'], 'ical_urls.*.label' => ['nullable', 'string', 'max:40'], 'ical_urls.*.url' => ['required', 'url', 'max:500'],
        ]);
        if (array_key_exists('min_nights', $d) && ! $d['min_nights']) {
            $d['min_nights'] = 1;
        }
        if (isset($d['seasons'])) {
            $d['seasons'] = array_values(array_map(fn ($x) => [
                'name' => trim((string) ($x['name'] ?? '')) ?: 'Haute saison', 'from' => $x['from'], 'to' => $x['to'],
                'price' => isset($x['price']) && $x['price'] !== '' ? (float) $x['price'] : null,
                'weekend_price' => isset($x['weekend_price']) && $x['weekend_price'] !== '' ? (float) $x['weekend_price'] : null,
                'min_nights' => ! empty($x['min_nights']) ? (int) $x['min_nights'] : null,
            ], $d['seasons']));
        }

        return $d;
    }

    private function republish(Site $site, SiteRenderer $renderer): void
    {
        if (! empty($site->site_data['content'])) {
            $renderer->publish($site);
        }
    }

    private function ownedSite(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user->isAdmin() || ($site->user_id && $site->user_id === $user->id) || ($site->owner_email && strtolower($site->owner_email) === strtolower($user->email)), 403);

        return $site;
    }
}
