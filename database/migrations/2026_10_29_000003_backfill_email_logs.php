<?php

use App\Console\Commands\NurtureLeads;
use App\Models\EmailLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Reprend dans le journal les emails envoyés avant son existence, à partir des dates gardées
 * sur chaque site (site prêt, relances, mise en ligne, messages manuels). Ouverture inconnue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', fn (Blueprint $t) => $t->boolean('tracked')->default(true)->after('opens'));

        Site::query()->whereNotNull('site_data')->orderBy('created_at')->chunk(200, function ($sites) {
            foreach ($sites as $site) {
                $e = (array) ($site->site_data['emails'] ?? []);
                $to = strtolower((string) ($site->owner_email ?: User::find($site->user_id)?->email));
                if (! $e || $to === '') {
                    continue;
                }
                $name = (string) $site->name;
                $items = [];
                if (! empty($e['welcome_at'])) {
                    $items[] = [$e['welcome_at'], $name.' : votre site est prêt 🎉'];
                }
                foreach (NurtureLeads::STEPS as $step => $def) {
                    if (! empty($e['nurture_'.$step])) {
                        $items[] = [$e['nurture_'.$step], $def[1]];
                    }
                }
                if (! empty($e['checkout_reminder_at'])) {
                    $items[] = [$e['checkout_reminder_at'], 'Il ne manque qu\'une étape pour mettre '.$name.' en ligne'];
                }
                if (! empty($e['live_at'])) {
                    $items[] = [$e['live_at'], $name.' est en ligne 🎉'];
                }
                foreach ((array) ($e['admin_messages'] ?? []) as $m) {
                    if (! empty($m['at']) && ! empty($m['subject'])) {
                        $items[] = [$m['at'], $m['subject']];
                    }
                }
                $userId = User::where('email', $to)->value('id');
                foreach ($items as [$at, $subject]) {
                    try {
                        $sent = Carbon::parse($at);
                    } catch (\Throwable) {
                        continue;
                    }
                    if (EmailLog::where('to', $to)->where('subject', $subject)->whereBetween('sent_at', [$sent->copy()->subMinutes(5), $sent->copy()->addMinutes(5)])->exists()) {
                        continue;
                    }
                    EmailLog::create(['to' => $to, 'subject' => mb_substr($subject, 0, 255), 'user_id' => $userId, 'sent_at' => $sent, 'tracked' => false]);
                }
            }
        });
    }

    public function down(): void
    {
        EmailLog::where('tracked', false)->delete();
        Schema::table('email_logs', fn (Blueprint $t) => $t->dropColumn('tracked'));
    }
};
