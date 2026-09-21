<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'reminder_sent' => 'boolean'];
}
