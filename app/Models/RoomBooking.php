<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBooking extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['check_in' => 'date', 'check_out' => 'date', 'total' => 'decimal:2'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
