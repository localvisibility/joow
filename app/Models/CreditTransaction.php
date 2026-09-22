<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Mouvement de crédits IA d'un site (journal). */
class CreditTransaction extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];
}
