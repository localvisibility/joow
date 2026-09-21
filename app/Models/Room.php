<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['photos' => 'array', 'amenities' => 'array', 'active' => 'boolean', 'price_night' => 'decimal:2', 'ical_synced_at' => 'datetime'];

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }
}
