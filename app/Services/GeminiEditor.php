<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Éditeur IA "conversationnel" : applique une instruction en langage naturel
 * au contenu structuré d'un site et renvoie le contenu mis à jour.
 */
class GeminiEditor
{
    private string $key;

    public function __construct()
    {
        $this->key = (string) config('services.gemini.key');
    }

    /**
     * @param  array  $content  Contenu structuré actuel (hero_title, services, faq…)
     * @param  string $instruction  Demande du client en langage naturel
     * @return array{content: array, accent: ?string, reply: string}
     */
    public function edit(array $content, string $instruction, array $context = []): array
    {
        if (! $this->key) {
            return ['content' => $content, 'accent' => null, 'reply' => "L'édition IA n'est pas configurée pour le moment."];
        }

        $current = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $name = $context['name'] ?? '';
        $sector = $context['sector'] ?? '';

        $prompt = <<<TXT
Tu es l'éditeur IA d'un site vitrine pour "{$name}" (secteur : {$sector}).
Tu reçois le CONTENU ACTUEL du site (JSON) et une INSTRUCTION du client.
Applique l'instruction en modifiant UNIQUEMENT ce qui est demandé, garde le reste identique.
Conserve exactement les mêmes clés JSON (hero_title, hero_subtitle, tagline, about_p1, about_p2,
badges[], stats[{v,l}], services[{name,desc,price}], faq[{q,a}], cta_text). Rédige en français,
ton professionnel et chaleureux. N'invente pas de fausses informations légales.

CONTENU ACTUEL :
{$current}

INSTRUCTION DU CLIENT :
{$instruction}

Réponds UNIQUEMENT avec un JSON valide de la forme :
{
  "content": { ...le contenu complet mis à jour, mêmes clés... },
  "accent": "code couleur hex si le client demande de changer la couleur, sinon null",
  "reply": "courte phrase expliquant ce que tu as modifié (1 phrase)"
}
TXT;

        try {
            $res = Http::timeout(45)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key='.$this->key,
                [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.5, 'responseMimeType' => 'application/json'],
                ]
            );

            $text = data_get($res->json(), 'candidates.0.content.parts.0.text');
            $data = $text ? json_decode($text, true) : null;

            if (! is_array($data) || ! isset($data['content']) || ! is_array($data['content'])) {
                return ['content' => $content, 'accent' => null, 'reply' => "Je n'ai pas pu appliquer cette demande. Reformulez-la ?"];
            }

            // Fusion défensive : on garde les clés existantes non renvoyées.
            $merged = array_merge($content, array_filter($data['content'], fn ($v) => $v !== null && $v !== ''));

            $accent = null;
            if (! empty($data['accent']) && preg_match('/^#?[0-9a-fA-F]{6}$/', trim($data['accent']))) {
                $accent = '#'.ltrim(trim($data['accent']), '#');
            }

            return [
                'content' => $merged,
                'accent'  => $accent,
                'reply'   => (string) ($data['reply'] ?? 'Modifications appliquées.'),
            ];
        } catch (\Throwable $e) {
            return ['content' => $content, 'accent' => null, 'reply' => 'Une erreur est survenue pendant l\'édition. Réessayez.'];
        }
    }
}
