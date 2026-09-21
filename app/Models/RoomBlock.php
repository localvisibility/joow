<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RoomBlock extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['start' => 'date', 'end' => 'date'];
}
