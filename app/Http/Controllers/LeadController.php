<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Réception des demandes (réservation / RDV / devis / contact) envoyées
 * depuis les sites générés (statiques) vers l'application Joow.
 */
class LeadController extends Controller
{
    /** Endpoint public appelé en fetch/CORS depuis <slug>.joow.fr. */
    public function store(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->first();

        $data = $request->validate([
            'type'    => ['nullable', 'string', 'max:20'],
            'name'    => ['nullable', 'string', 'max:120'],
            'email'   => ['nullable', 'email', 'max:160'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
            'payload' => ['nullable', 'array'],
            'hp'      => ['nullable', 'string', 'max:0'], // honeypot anti-spam
        ]);

        if (! empty($request->input('hp'))) {
            return response()->json(['ok' => true]); // piège à bots : on ignore
        }

        $lead = Lead::create([
            'site_id'   => $site?->id,
            'site_slug' => $slug,
            'type'      => $data['type'] ?? 'contact',
            'name'      => $data['name'] ?? null,
            'email'     => $data['email'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'message'   => $data['message'] ?? null,
            'payload'   => $data['payload'] ?? null,
            'ip'        => $request->ip(),
        ]);

        \App\Models\SiteStat::bump($slug, 'leads');
        if ($site) {
            app(\App\Services\Notifier::class)->leadReceived($site, $lead);
        }

        return response()->json(['ok' => true, 'id' => $lead->id]);
    }

    private function notifyOwner(?Site $site, Lead $lead): void
    {
        $to = $site?->owner_email ?: $site?->email;
        if (! $to) {
            return;
        }

        try {
            $lines = array_filter([
                "Nouvelle demande depuis votre site {$site->name}.",
                'Type : '.$lead->type,
                $lead->name ? 'Nom : '.$lead->name : null,
                $lead->email ? 'Email : '.$lead->email : null,
                $lead->phone ? 'Téléphone : '.$lead->phone : null,
                $lead->message ? "Message :\n".$lead->message : null,
                $lead->payload ? 'Détails : '.json_encode($lead->payload, JSON_UNESCAPED_UNICODE) : null,
            ]);

            Mail::raw(implode("\n", $lines), function ($m) use ($to, $site) {
                $m->to($to)->subject('Nouvelle demande — '.$site->name);
                if ($site->email && $site->email !== $to) {
                    $m->replyTo($site->email);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Lead notify failed: '.$e->getMessage());
        }
    }
}
