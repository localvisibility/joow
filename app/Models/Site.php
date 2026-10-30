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
        'stripe_charges_enabled' => 'boolean',
        'cancel_at_period_end' => 'boolean',
        'rating'              => 'float',
        'paid_at'             => 'datetime',
        'payment_failed_at'   => 'datetime',
        'published_at'        => 'datetime',
        'domain_expires_at'   => 'datetime',
        'subscription_period_end' => 'datetime',
    ];

    /** Jeton d'édition (lien signé envoyé par email) : accès au Studio sans compte. */
    public function editToken(): string
    {
        return substr(hash_hmac('sha256', 'edit:'.$this->id, (string) config('app.key')), 0, 40);
    }

    /** L'utilisateur (ou la session invitée) peut-il modifier ce site dans le Studio ? */
    /**
     * Identifiant du site, aussi sous-domaine <slug>.joow.fr : un label DNS fait 63 caractères au plus.
     * Nom coupé proprement (~40 caractères, sur un tiret) + 4 caractères aléatoires, unique.
     */
    public static function makeSlug(string $name, int $max = 40): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'site';
        if (strlen($base) > $max) {
            $cut = substr($base, 0, $max + 1);
            $base = strrpos($cut, '-') > 12 ? substr($cut, 0, strrpos($cut, '-')) : substr($base, 0, $max);
        }
        $base = trim($base, '-') ?: 'site';
        do {
            $slug = $base.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Activité du prospect sur son site (aperçu, site plein écran, Studio, modifications…) :
     * compteur + première / dernière date, dans site_data.activity. Ignore l'équipe Joow.
     */
    public function track(string $event): void
    {
        $req = request();
        if ($req && ($req->user()?->isAdmin() || ($req->hasSession() && $req->session()->get('admin_impersonator')))) {
            return;
        }
        $data = $this->site_data ?? [];
        $a = $data['activity'][$event] ?? ['n' => 0, 'first' => null, 'last' => null];
        // Une même personne qui recharge : on ne compte qu'une fois toutes les 10 minutes
        if ($a['last'] && now()->diffInMinutes(\Carbon\Carbon::parse($a['last'])) < 10) {
            return;
        }
        $a['n'] = (int) $a['n'] + 1;
        $a['first'] ??= now()->toIso8601String();
        $a['last'] = now()->toIso8601String();
        $data['activity'][$event] = $a;
        $this->timestamps = false;
        $this->forceFill(['site_data' => $data])->saveQuietly();
        $this->timestamps = true;
        app(\App\Services\HotLeads::class)->notify($this, $event);
    }

    /** Site renommé (identifiant raccourci) : retrouve-le par son ancien identifiant. */
    public static function byOldSlug(string $slug): ?self
    {
        return static::whereJsonContains('site_data->old_slugs', $slug)->first();
    }

    public function editableBy(?User $user, array $guestSlugs = []): bool
    {
        if ($user) {
            if ($user->isAdmin() || ($this->user_id && $this->user_id === $user->id)) {
                return true;
            }
            if ($this->owner_email && strtolower($this->owner_email) === strtolower($user->email)) {
                return true;
            }
        }

        return in_array($this->slug, $guestSlugs, true);
    }

    /** Rattache à un compte les sites créés sans compte (même email ou même session). */
    public static function claimFor(User $user, array $guestSlugs = []): int
    {
        $q = static::whereNull('user_id')->where(function ($q) use ($user, $guestSlugs) {
            $q->whereRaw('LOWER(owner_email) = ?', [strtolower($user->email)]);
            if ($guestSlugs) {
                $q->orWhereIn('slug', $guestSlugs);
            }
        });

        return $q->update(['user_id' => $user->id, 'owner_email' => $user->email]);
    }

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
