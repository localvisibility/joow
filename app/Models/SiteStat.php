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
        $find = fn () => static::where('site_slug', $slug)->whereDate('day', $day)->first();
        try {
            $row = $find() ?? static::create(['site_slug' => $slug, 'day' => $day]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $row = $find(); // créée au même instant par une autre requête
        }
        $row?->increment($column, $by);
    }
}
