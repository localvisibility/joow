<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Assistant d'aide du tableau de bord : répond aux questions des clients sur Joow (Studio, publication,
 * domaine, facturation, modules…) à partir de la base de connaissances resources/content/help.md
 * et de la situation réelle du client (ses sites, formules, domaines).
 */
class SupportBot
{
    public function knowledge(): string
    {
        return Cache::remember('joow:help-kb:'.@filemtime(resource_path('content/help.md')), now()->addDay(), fn () => (string) @file_get_contents(resource_path('content/help.md')));
    }

    public function context(User $user): string
    {
        $sites = Site::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))->latest()->take(8)->get();
        if ($sites->isEmpty()) {
            return 'Le client n\'a pas encore de site.';
        }

        return "Sites du client :\n".$sites->map(function (Site $s) {
            $status = ['paid' => 'en ligne (payé)', 'published' => 'en ligne', 'preview' => 'aperçu gratuit, pas encore en ligne', 'ready' => 'aperçu gratuit', 'generating' => 'en cours de génération', 'failed' => 'échec de génération'][$s->status] ?? $s->status;
            $plan = $s->hosting_plan ? ' · formule '.($s->hosting_plan === 'liberte' ? 'Liberté' : 'Pro').($s->subscription_status ? ' ('.$s->subscription_status.')' : '') : '';
            $dom = $s->custom_domain ? ' · domaine '.$s->custom_domain.' ('.($s->domain_status ?: 'en cours').')' : '';
            $ads = ! empty($s->site_data['addons']['ads']['active']) ? ' · Joow Ads actif' : '';

            return "- {$s->name} : {$status}{$plan}{$dom}{$ads} · adresse https://{$s->slug}.joow.fr";
        })->implode("\n");
    }

    /** @return array{answer: string, escalate: bool} */
    public function answer(User $user, string $question, array $history = []): array
    {
        $conv = collect($history)->take(-8)->map(fn ($m) => (($m['role'] ?? '') === 'user' ? 'Client' : 'Assistant').' : '.Str::limit((string) ($m['text'] ?? ''), 600))->implode("\n");
        $prompt = <<<TXT
Tu es l'assistant d'aide de Joow, pour les clients connectés à leur espace. Réponds en français, avec chaleur et précision,
en t'appuyant UNIQUEMENT sur la base de connaissances et la situation du client ci-dessous. Donne des étapes concrètes
(menu → bouton) quand c'est utile, en 2 à 8 phrases ou une courte liste. Pas de jargon technique.
Si l'information n'est pas dans la base, si c'est un bug, un remboursement, une demande sur mesure, ou si le client est mécontent :
dis honnêtement que l'équipe va l'aider et invite-le à utiliser « Écrire à l'équipe », et mets "escalate": true.
N'invente jamais de prix, de délai ni de fonctionnalité.

=== BASE DE CONNAISSANCES ===
{$this->knowledge()}

=== SITUATION DU CLIENT ({$user->name}, {$user->email}) ===
{$this->context($user)}

=== CONVERSATION ===
{$conv}
Client : {$question}

Réponds UNIQUEMENT en JSON : {"answer": "ta réponse (markdown simple autorisé : **gras**, listes)", "escalate": false}
TXT;
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.3, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 1500, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 45, 'SupportBot', fast: true);
            $data = json_decode((string) Gemini::text($res), true);
            if (is_array($data) && trim((string) ($data['answer'] ?? '')) !== '') {
                return ['answer' => trim((string) $data['answer']), 'escalate' => (bool) ($data['escalate'] ?? false)];
            }
        } catch (\Throwable $e) {
            Log::warning('SupportBot : '.$e->getMessage());
        }

        return ['answer' => "Je n'arrive pas à répondre pour le moment. Écrivez-nous avec le formulaire « Écrire à l'équipe » : nous vous répondons sous 24 h ouvrées.", 'escalate' => true];
    }
}
