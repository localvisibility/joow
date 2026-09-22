<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publication d'un site sur disque par le worker (qui a les droits d'écriture),
 * utilisée en repli quand le conteneur web ne peut pas écrire directement.
 */
class PublishSiteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [5, 20, 60];
    }

    public function __construct(public string $siteId) {}

    public function handle(SiteRenderer $renderer): void
    {
        $site = Site::find($this->siteId);
        if (! $site) {
            return;
        }

        $all = $renderer->renderAll($site);
        $site->update(['html_content' => $all['']]);
        $renderer->writeAll($site->slug, $all);
    }
}
