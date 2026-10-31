<?php

namespace App\Jobs;

use App\Http\Controllers\AdsController;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Tâche IA de Joow Ads (stratégie, campagne, page d'offre) exécutée en arrière-plan : l'écran suit son état. */
class AdsTaskJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public string $task, public string $action, public string $siteId, public int $userId, public array $data) {}

    public function handle(): void
    {
        $site = Site::find($this->siteId);
        try {
            $out = $site ? app(AdsController::class)->runTask($this->action, $site, $this->data)
                : ['error' => 'Site introuvable.'];
        } catch (HttpExceptionInterface $e) {
            $out = ['error' => $e->getMessage() ?: 'La génération n\'a pas abouti, réessayez dans un instant.'];
        } catch (\Throwable $e) {
            Log::warning("AdsTaskJob {$this->action} : ".$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
            $out = ['error' => 'La génération n\'a pas abouti, réessayez dans un instant.'];
        }
        self::put($this->task, $this->userId, isset($out['error']) ? ['status' => 'error', 'error' => $out['error']] : ['status' => 'done', 'result' => $out]);
    }

    public function failed(\Throwable $e): void
    {
        self::put($this->task, $this->userId, ['status' => 'error', 'error' => 'La génération a pris trop de temps, réessayez.']);
    }

    public static function put(string $task, int $userId, array $state): void
    {
        Cache::put("ads-task:$task", $state + ['user' => $userId], now()->addHour());
    }
}
