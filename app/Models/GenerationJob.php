<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GenerationJob extends Model
{
    use HasUuids;

    protected $guarded = [];

    /** Jamais de clé Google dans les données enregistrées : photos Places réécrites vers /gphoto. */
    protected static function booted(): void
    {
        static::saving(function ($m) {
            if ($m->isDirty('result') && str_contains((string) json_encode($m->result), 'places.googleapis.com')) {
                $m->result = \App\Services\PlacesPhoto::scrub($m->result);
            }
        });
    }

    protected $casts = [
        'input'        => 'array',
        'result'       => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];
}
