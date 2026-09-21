<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS transactionnels via Brevo (confirmations, rappels).
 * Silencieux si BREVO_API_KEY n'est pas configurée.
 */
class SmsSender
{
    public function enabled(): bool
    {
        return (bool) config('services.brevo.key');
    }

    /** Numéro FR/international → format E.164 sans "+" (attendu par Brevo). */
    public static function normalize(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0') && strlen($d) === 10) {
            $d = '33'.substr($d, 1);
        }
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }

        return strlen($d) >= 10 ? $d : null;
    }

    public function send(?string $to, string $text, ?string $sender = null): bool
    {
        $to = self::normalize($to);
        if (! $to || ! $this->enabled()) {
            return false;
        }
        $sender = preg_replace('/[^A-Za-z0-9]/', '', $sender ?: (string) config('services.brevo.sms_sender', 'Joow'));
        $sender = substr($sender ?: 'Joow', 0, 11);

        try {
            $res = Http::timeout(15)->withHeaders(['api-key' => config('services.brevo.key'), 'accept' => 'application/json'])
                ->post('https://api.brevo.com/v3/transactionalSMS/sms', [
                    'sender'    => $sender,
                    'recipient' => $to,
                    'content'   => mb_substr($text, 0, 320),
                    'type'      => 'transactional',
                ]);
            if (! $res->successful()) {
                Log::warning('SMS Brevo: '.$res->status().' '.$res->body());
            }

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('SMS Brevo: '.$e->getMessage());

            return false;
        }
    }
}
