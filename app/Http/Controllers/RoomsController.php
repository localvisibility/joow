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
        $rooms = $site->rooms()->get();
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
        foreach ($site->rooms()->whereNotNull('ical_url')->get() as $room) {
            $n += $ical->syncRoom($room);
        }

        return response()->json(['ok' => true, 'events' => $n]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'], 'price_night' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array', 'max:8'], 'photos.*' => ['url'], 'amenities' => ['nullable', 'array', 'max:20'], 'amenities.*' => ['string', 'max:40'],
            'ical_url' => ['nullable', 'url', 'max:500'], 'active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer'],
        ]);
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
