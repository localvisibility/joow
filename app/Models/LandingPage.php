<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['variants' => 'array', 'content' => 'array', 'published_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Adresse publique (domaine du client s'il est actif, sinon <slug>.joow.fr). */
    public function url(): string
    {
        $site = $this->site;
        $host = $site && $site->custom_domain && $site->domain_status === 'active' ? $site->custom_domain : $this->site_slug.'.joow.fr';

        return 'https://'.$host.'/o/'.$this->path.'/';
    }
}
