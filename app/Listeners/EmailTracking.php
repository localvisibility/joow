<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Journalise chaque email envoyé à un compte Joow (prospect ou client) et y ajoute un pixel
 * d'ouverture. Les emails destinés aux clients des professionnels (réservations, devis…) ne
 * sont ni suivis ni journalisés ici.
 */
class EmailTracking
{
    public function handle(MessageSending $event): void
    {
        try {
            $msg = $event->message;
            $to = strtolower((string) ($msg->getTo()[0] ?? null)?->getAddress());
            if ($to === '' || in_array($to, (array) config('joow.admins'), true)) {
                return;
            }
            $user = User::where('email', $to)->first(['id']);
            if (! $user) {
                return;
            }
            $log = EmailLog::create(['to' => $to, 'subject' => mb_substr((string) $msg->getSubject(), 0, 255), 'user_id' => $user->id, 'sent_at' => now()]);

            $html = $msg->getHtmlBody();
            if (is_string($html) && $html !== '') {
                $pixel = '<img src="'.route('email.open', $log->id).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;opacity:0">';
                $msg->html(str_contains($html, '</body>') ? str_replace('</body>', $pixel.'</body>', $html) : $html.$pixel);
            }
        } catch (\Throwable $e) {
            Log::info('Suivi email : '.$e->getMessage());
        }
    }
}
