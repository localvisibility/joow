<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Demandes de support : création, fil de réponses, notifications.
 * Statuts : open (à traiter par l'équipe) → answered (réponse envoyée) → closed.
 */
class SupportDesk
{
    public function create(User $user, string $subject, string $message, ?string $site = null): int
    {
        $id = DB::table('support_tickets')->insertGetId(['user_id' => $user->id, 'site_slug' => $site, 'subject' => $subject, 'message' => $message, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->notifyTeam($id, $user, "Nouvelle demande : $subject", $message, $site);

        return $id;
    }

    /** Réponse de l'équipe : enregistrée, envoyée par email au client, demande passée « répondu ». */
    public function answer(int $ticketId, User $admin, string $message, bool $close = false): void
    {
        $t = DB::table('support_tickets')->find($ticketId);
        abort_unless($t, 404);
        DB::table('support_replies')->insert(['ticket_id' => $ticketId, 'user_id' => $admin->id, 'author' => 'team', 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('support_tickets')->where('id', $ticketId)->update(['status' => $close ? 'closed' : 'answered', 'updated_at' => now()]);

        $client = User::find($t->user_id);
        if (! $client) {
            return;
        }
        try {
            app(Accounts::class)->send($client, "Re : {$t->subject} (demande #{$ticketId})", [
                'preheader' => 'L\'équipe Joow vous a répondu.',
                'title' => 'L\'équipe Joow vous a répondu',
                'intro' => $message,
                'rows' => [['Votre demande', $t->subject]],
                'cta' => ['label' => 'Voir la conversation', 'url' => app(Accounts::class)->magicLink($client, '/support', 14)],
                'note' => 'Vous pouvez répondre depuis votre espace (menu Support) ou directement à cet email.',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Support #$ticketId : envoi de la réponse impossible : ".$e->getMessage());
        }
    }

    /** Réponse du client : la demande repasse « à traiter » et l'équipe est prévenue. */
    public function clientReply(int $ticketId, User $client, string $message): void
    {
        $t = DB::table('support_tickets')->where('id', $ticketId)->where('user_id', $client->id)->first();
        abort_unless($t, 404);
        DB::table('support_replies')->insert(['ticket_id' => $ticketId, 'user_id' => $client->id, 'author' => 'client', 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('support_tickets')->where('id', $ticketId)->update(['status' => 'open', 'updated_at' => now()]);
        $this->notifyTeam($ticketId, $client, "Nouvelle réponse : {$t->subject}", $message, $t->site_slug);
    }

    /** Fil complet d'une demande (message initial + réponses). */
    public function thread(object $t): array
    {
        $first = [['author' => 'client', 'message' => $t->message, 'at' => \Illuminate\Support\Carbon::parse($t->created_at)->toIso8601String()]];
        $replies = DB::table('support_replies')->where('ticket_id', $t->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn ($r) => ['author' => $r->author, 'message' => $r->message, 'at' => \Illuminate\Support\Carbon::parse($r->created_at)->toIso8601String()])->all();

        return array_merge($first, $replies);
    }

    private function notifyTeam(int $id, User $user, string $title, string $message, ?string $site): void
    {
        $body = implode("\n", array_filter([
            "$title",
            '',
            "De : {$user->name} <{$user->email}>",
            $site ? "Site : https://$site.joow.fr" : null,
            '',
            '« '.$message.' »',
            '',
            'Répondre depuis le back-office : '.route('admin.index', ['tab' => 'support']),
            '(ou répondez directement à cet email : la réponse partira chez le client)',
        ], fn ($l) => $l !== null));
        foreach (array_filter((array) config('joow.admins')) ?: ['contact@joow.fr'] as $to) {
            try {
                Mail::raw($body, fn ($m) => $m->to($to)->replyTo($user->email, $user->name)->subject("🛟 Support #{$id} · {$title}"));
            } catch (\Throwable $e) {
                Log::warning("Support #$id : notification impossible : ".$e->getMessage());
            }
        }
    }
}
