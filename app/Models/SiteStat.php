<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteStat extends Model
{
    protected $guarded = [];

    protected $casts = ['day' => 'date'];

    /** Incrémente un compteur journalier (upsert atomique). */
    public static function bump(string $slug, string $column, int $by = 1): void
    {
        $day = now()->toDateString();
        $row = static::firstOrCreate(['site_slug' => $slug, 'day' => $day]);
        $row->increment($column, $by);
    }
}
