<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'pages'               => 'array',
        'site_data'           => 'array',
        'modules'             => 'array',
        'legal_content'       => 'array',
        'contact_form_config' => 'array',
        'domain_verified'     => 'boolean',
        'domain_ssl_active'   => 'boolean',
        'cancel_at_period_end' => 'boolean',
        'rating'              => 'float',
        'paid_at'             => 'datetime',
        'payment_failed_at'   => 'datetime',
        'published_at'        => 'datetime',
        'subscription_period_end' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'site_slug', 'slug');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'site_slug', 'slug');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'site_slug', 'slug')->orderBy('sort_order');
    }

    public function roomBookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class, 'site_slug', 'slug');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'site_slug', 'slug')->orderBy('category')->orderBy('sort_order');
    }

    /** Configuration d'un module (tableau) — `booking` reste un simple booléen historique. */
    public function module(string $key): array
    {
        $m = $this->modules ?? [];
        $v = $m[$key] ?? null;
        if (is_bool($v)) {
            return ['enabled' => $v];
        }

        return is_array($v) ? $v : [];
    }

    public function moduleEnabled(string $key): bool
    {
        $cfg = config("modules.$key", []);
        if (! empty($cfg['always_on'])) {
            return true;
        }
        $m = $this->modules ?? [];
        if (! array_key_exists($key, $m)) {
            // Défauts : formulaire de demande + avis actifs
            return in_array($key, ['booking', 'reviews'], true);
        }

        return (bool) ($this->module($key)['enabled'] ?? false);
    }

    public function setModule(string $key, array $config): void
    {
        $m = $this->modules ?? [];
        $m[$key] = array_merge($this->module($key), $config);
        $this->modules = $m;
        $this->save();
    }
}
