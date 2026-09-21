<?php

namespace App\Services\Modules;

use App\Models\Site;
use Illuminate\Support\Facades\Http;

/**
 * Assistant IA du site : répond aux visiteurs à partir des informations
 * de l'établissement (contenu, horaires, services, carte, FAQ, infos pratiques).
 */
class BotAnswer
{
    public static function defaults(): array
    {
        return [
            'enabled'      => false,
            'name'         => 'Assistant',
            'welcome'      => 'Bonjour 👋 Une question ? Je vous réponds tout de suite.',
            'tone'         => 'chaleureux',
            'color'        => null,
            'position'     => 'right',
            'practical'    => '',   // infos pratiques libres (parking, accès…)
            'faq'          => '',   // Q/R supplémentaires
            'lead_capture' => true,
        ];
    }

    /** @param array<int, array{role:string,text:string}> $history */
    public function answer(Site $site, string $question, array $history = []): string
    {
        $key = (string) config('services.gemini.key');
        $cfg = array_replace(self::defaults(), $site->module('bot'));
        if (! $key) {
            return "Je ne peux pas répondre pour le moment. Contactez-nous directement, nous serons ravis de vous aider.";
        }

        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $c = $d['content'] ?? [];
        $menu = $site->menuItems()->where('available', true)->get()->groupBy('category')
            ->map(fn ($g, $cat) => $cat.' : '.$g->map(fn ($i) => $i->name.($i->price ? ' ('.number_format((float) $i->price, 2, ',', ' ').' €)' : ''))->implode(', '))
            ->implode("\n");
        $rooms = $site->rooms()->where('active', true)->get()
            ->map(fn ($r) => $r->name.' — '.$r->capacity.' pers.'.($r->price_night ? ', '.number_format((float) $r->price_night, 0, ',', ' ').' €/nuit' : ''))->implode("\n");

        $knowledge = collect([
            'Établissement' => $b['name'] ?? $site->name,
            'Secteur' => $site->sector,
            'Adresse' => $b['address'] ?? null,
            'Téléphone' => $b['phone'] ?? null,
            'Horaires' => implode(' ; ', $b['opening_hours'] ?? []),
            'Note Google' => isset($b['rating']) ? $b['rating'].'/5 ('.($b['reviews_count'] ?? 0).' avis)' : null,
            'Présentation' => trim(($c['about_p1'] ?? '').' '.($c['about_p2'] ?? '')),
            'Services' => collect($c['services'] ?? [])->map(fn ($s) => ($s['name'] ?? '').' : '.($s['desc'] ?? '').' ('.($s['price'] ?? '').')')->implode(' | '),
            'FAQ' => collect($c['faq'] ?? [])->map(fn ($f) => 'Q: '.($f['q'] ?? '').' R: '.($f['a'] ?? ''))->implode(' | '),
            'Carte / menu' => $menu ?: null,
            'Chambres' => $rooms ?: null,
            'Infos pratiques' => $cfg['practical'] ?: null,
            'Questions/réponses complémentaires' => $cfg['faq'] ?: null,
        ])->filter()->map(fn ($v, $k) => "$k : $v")->implode("\n");

        $hist = collect($history)->take(-8)->map(fn ($m) => ($m['role'] === 'user' ? 'Visiteur' : 'Assistant').' : '.$m['text'])->implode("\n");
        $lead = $cfg['lead_capture'] ? "Si le visiteur semble intéressé (réservation, devis, rendez-vous), propose-lui poliment de laisser son téléphone ou email, ou de remplir le formulaire du site." : '';

        $prompt = <<<TXT
Tu es "{$cfg['name']}", l'assistant du site de {$b['name']}. Tu réponds aux visiteurs en français, ton {$cfg['tone']}, réponses courtes (2-4 phrases), sans inventer : si l'information n'est pas dans les données ci-dessous, dis-le et invite à contacter l'établissement (téléphone ou formulaire). {$lead}

DONNÉES DE L'ÉTABLISSEMENT :
{$knowledge}

HISTORIQUE :
{$hist}

Visiteur : {$question}
Assistant :
TXT;

        try {
            $res = Http::timeout(30)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key='.$key,
                ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 300]]
            );
            $text = trim((string) data_get($res->json(), 'candidates.0.content.parts.0.text'));

            return $text ?: "Je n'ai pas bien compris. Pouvez-vous reformuler ?";
        } catch (\Throwable) {
            return "Petit souci technique de mon côté. Contactez-nous directement, nous vous répondrons rapidement.";
        }
    }
}
