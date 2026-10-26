<?php

namespace App\Console\Commands;

use App\Services\MetaConversions;
use Illuminate\Console\Command;

/** Envoie un événement de test à l'API de conversions (visible dans Events Manager → Tester les événements). */
class MetaTestEvent extends Command
{
    protected $signature = 'meta:test {--code= : Code de test (TESTxxxxx) affiché dans Events Manager} {--event=Purchase}';

    protected $description = 'Vérifie la liaison avec l\'API de conversions Meta';

    public function handle(MetaConversions $meta): int
    {
        if (! config('services.meta.pixel_id') || ! config('services.meta.access_token')) {
            $this->error('META_PIXEL_ID et META_CAPI_TOKEN doivent être renseignés dans le .env.');

            return self::FAILURE;
        }
        $res = $meta->post([[
            'event_name' => $this->option('event'), 'event_time' => time(), 'event_id' => 'test-'.time(), 'action_source' => 'website',
            'event_source_url' => 'https://app.joow.fr/', 'user_data' => ['em' => [hash('sha256', 'test@joow.fr')], 'client_user_agent' => 'JoowTest/1.0', 'client_ip_address' => '1.2.3.4'],
            'custom_data' => ['value' => 39, 'currency' => 'EUR', 'content_ids' => ['pro'], 'content_type' => 'product'],
        ]], $this->option('code'));
        $this->line(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return isset($res['events_received']) ? self::SUCCESS : self::FAILURE;
    }
}
