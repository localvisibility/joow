<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_ht'  => 'decimal:2',
        'tva_rate'  => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function getAmountTtcAttribute(): float
    {
        return round((float) $this->price_ht * (1 + (float) $this->tva_rate / 100), 2);
    }
}
