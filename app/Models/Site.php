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
}
