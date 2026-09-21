<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['available' => 'boolean', 'price' => 'decimal:2'];
}
