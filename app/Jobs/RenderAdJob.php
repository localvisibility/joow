<?php

namespace App\Jobs;

use App\Models\AdsPack;
use App\Models\Site;
use App\Services\Ads\AdCreative;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Création d'une pub en arrière-plan (modèle image Pro + contrôle qualité : souvent plus d'une minute). */
class RenderAdJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 330;

    public int $tries = 1;

    public function __construct(public string $packId, public string $key, public string $format, public bool $admin = false, public bool $redo = false) {}

    public function handle(AdCreative $creative): void
    {
        $pack = AdsPack::find($this->packId);
        $site = $pack ? Site::find($pack->site_id) : null;
        if (! $pack || ! $site) {
            return;
        }
        $idx = collect($pack->pack['campaign']['creatives'] ?? [])->search(fn ($c) => ($c['key'] ?? '') === $this->key);
        if ($idx === false) {
            return;
        }
        $c = $pack->pack['campaign']['creatives'][$idx];
        $from = $this->format !== 'portrait' && ! $this->redo ? ($c['renders']['portrait']['url'] ?? null) : null;
        try {
            $out = $creative->render($site, $c, $this->format, $this->admin, $from);
        } catch (\Throwable $e) {
            Log::warning("RenderAdJob {$site->slug} {$this->key} : ".$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
            $out = ['error' => 'La création de la pub n\'a pas abouti. Réessayez dans un instant (rien n\'a été décompté).', 'detail' => 'Exception : '.str($e->getMessage())->limit(200).' @ '.basename($e->getFile()).':'.$e->getLine()];
        }
        self::store($this->packId, $this->key, function (array &$cr) use ($out) {
            if (isset($out['error'])) {
                $cr['jobs'][$this->format] = ['status' => 'error', 'error' => $out['error']] + ($this->admin && ! empty($out['detail']) ? ['detail' => $out['detail']] : []);

                return;
            }
            if ($this->format === 'portrait') {
                $cr['renders'] = []; // nouvelle version principale : les déclinaisons précédentes ne correspondent plus
            }
            $cr['renders'][$this->format] = ['url' => $out['url'], 'qa' => $out['qa'], 'model' => $out['model'] ?? null];
            unset($cr['jobs'][$this->format]);
        });
    }

    public function failed(\Throwable $e): void
    {
        self::store($this->packId, $this->key, function (array &$cr) {
            $cr['jobs'][$this->format] = ['status' => 'error', 'error' => 'La création a pris trop de temps. Réessayez.'];
        });
    }

    /** Mise à jour atomique d'un concept dans le pack (relu juste avant l'écriture). */
    public static function store(string $packId, string $key, callable $fn): void
    {
        $pack = AdsPack::find($packId);
        if (! $pack) {
            return;
        }
        $p = (array) $pack->pack;
        foreach ((array) ($p['campaign']['creatives'] ?? []) as $i => $cr) {
            if (($cr['key'] ?? '') === $key) {
                $fn($p['campaign']['creatives'][$i]);
            }
        }
        $pack->pack = $p;
        $pack->save();
    }
}
