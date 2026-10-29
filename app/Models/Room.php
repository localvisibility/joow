<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'photos' => 'array', 'amenities' => 'array', 'active' => 'boolean', 'price_night' => 'decimal:2', 'ical_synced_at' => 'datetime',
        'weekend_price' => 'decimal:2', 'seasons' => 'array', 'min_nights' => 'integer', 'max_nights' => 'integer', 'cleaning_fee' => 'decimal:2',
        'included_guests' => 'integer', 'extra_guest_fee' => 'decimal:2', 'tourist_tax' => 'decimal:2', 'ical_urls' => 'array',
    ];

    /** Flux iCal importés (Airbnb, Booking, Bed-Booking…) : ancien champ unique + liste. */
    public function icalFeeds(): array
    {
        $feeds = [];
        foreach (array_merge($this->ical_url ? [['label' => 'iCal', 'url' => $this->ical_url]] : [], (array) ($this->ical_urls ?? [])) as $f) {
            $url = trim((string) ($f['url'] ?? ''));
            if ($url !== '' && ! in_array($url, array_column($feeds, 'url'), true)) {
                $feeds[] = ['label' => (string) ($f['label'] ?? '') ?: 'iCal', 'url' => $url];
            }
        }

        return $feeds;
    }

    /** Jeton du flux iCal exporté (créé à la demande). */
    public function exportToken(): string
    {
        if (! $this->ical_token) {
            $this->forceFill(['ical_token' => \Illuminate\Support\Str::random(40)])->save();
        }

        return $this->ical_token;
    }

    public function exportUrl(): string
    {
        return route('public.room.ical', ['room' => $this->id, 'token' => $this->exportToken()]);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }
}
