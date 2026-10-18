<?php

namespace App\Services\Modules;

use App\Models\Site;
use App\Services\Pages\PageSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Assistant IA du site : répond aux visiteurs à partir de TOUT le contenu du site
 * (accueil, services, FAQ, pages, carte, chambres, avis, horaires, réservation) et des
 * informations complémentaires saisies par le propriétaire (infos pratiques, Q/R, consignes).
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
            'instructions' => '',   // consignes du propriétaire (à faire / à éviter)
            'lead_capture' => true,
        ];
    }

    /** @param array<int, array{role:string,text:string}> $history */
    public function answer(Site $site, string $question, array $history = [], string $page = ''): string
    {
        $key = (string) config('services.gemini.key');
        $cfg = array_replace(self::defaults(), $site->module('bot'));
        if (! $key) {
            return "Je ne peux pas répondre pour le moment. Contactez-nous directement, nous serons ravis de vous aider.";
        }

        $knowledge = $this->knowledge($site, $cfg);
        $hist = collect($history)->take(-8)->map(fn ($m) => ($m['role'] === 'user' ? 'Visiteur' : 'Assistant').' : '.$m['text'])->implode("\n");
        $name = $site->site_data['business']['name'] ?? $site->name;
        $lead = $cfg['lead_capture']
            ? "Si le visiteur semble intéressé (réservation, devis, rendez-vous, groupe), propose-lui l'action du site correspondante et, s'il hésite, de laisser son téléphone ou son email pour être rappelé."
            : '';
        $rules = trim((string) $cfg['instructions']) !== '' ? "CONSIGNES DU PROPRIÉTAIRE (prioritaires) :\n".trim($cfg['instructions'])."\n" : '';
        $now = now()->locale('fr')->isoFormat('dddd D MMMM YYYY, HH[h]mm');
        $where = $page && $page !== '/' ? "Le visiteur se trouve sur la page « {$page} » du site." : 'Le visiteur se trouve sur la page d\'accueil du site.';

        $prompt = <<<TXT
Tu es "{$cfg['name']}", l'assistant du site web de {$name}. Tu parles au nom de l'établissement (« nous »), en français (ou dans la langue du visiteur s'il écrit dans une autre langue), ton {$cfg['tone']}.

RÈGLES :
- Réponds de façon précise et utile, 1 à 4 phrases, sans formule creuse. Va droit au but.
- Appuie-toi UNIQUEMENT sur les données ci-dessous. Si l'information n'y est pas, dis-le simplement et oriente vers le téléphone ou le formulaire : n'invente jamais un prix, un horaire, une disponibilité ou une politique.
- Pour les disponibilités en temps réel (une table ce soir, une chambre à une date), tu ne peux pas vérifier : renvoie vers la réservation en ligne ou le téléphone.
- Quand c'est pertinent, termine par une action concrète sous la forme d'un lien : [[#reserver|Réserver]], [[#contact|Nous contacter]], [[#carte|Voir la carte]], [[#services|Nos services]], [[#avis|Les avis]], [[#chambres|Les chambres]], ou [[tel:NUMÉRO|Appeler]] avec le numéro de l'établissement. N'utilise que les ancres listées dans « Sections disponibles ».
- Nous sommes le {$now}. {$where} Utilise « État actuel » pour dire si c'est ouvert maintenant.
- {$lead}
{$rules}
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
                // thinkingBudget 0 : réponse directe (les tokens de réflexion comptaient dans la limite et tronquaient la réponse)
                ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.35, 'maxOutputTokens' => 1024, 'thinkingConfig' => ['thinkingBudget' => 0]]]
            );
            if ($res->failed()) {
                Log::warning('Bot Gemini : HTTP '.$res->status().' '.Str::limit($res->body(), 300));
            }
            $text = trim((string) data_get($res->json(), 'candidates.0.content.parts.0.text'));

            return $text ?: "Je n'ai pas bien compris. Pouvez-vous reformuler ?";
        } catch (\Throwable $e) {
            Log::warning('Bot Gemini : '.$e->getMessage());

            return "Petit souci technique de mon côté. Contactez-nous directement, nous vous répondrons rapidement.";
        }
    }

    /** Analyse complète du site → texte structuré pour le modèle. */
    public function knowledge(Site $site, array $cfg): string
    {
        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $c = $d['content'] ?? [];
        $modules = $site->modules ?? [];
        $on = fn ($k) => $site->moduleEnabled($k);
        $mod = fn ($k) => is_array($modules[$k] ?? null) ? $modules[$k] : [];

        $menu = $site->menuItems()->where('available', true)->get()->groupBy('category')
            ->map(fn ($g, $cat) => $cat.' : '.$g->map(fn ($i) => $i->name.($i->price ? ' ('.number_format((float) $i->price, 2, ',', ' ').' €)' : '').(! empty($i->description) ? ' — '.$i->description : ''))->implode(' ; '))
            ->implode("\n");
        $rooms = $site->rooms()->where('active', true)->get()
            ->map(fn ($r) => $r->name.' — '.$r->capacity.' pers.'.($r->price_night ? ', '.number_format((float) $r->price_night, 0, ',', ' ').' €/nuit' : '').(! empty($r->description) ? ' — '.Str::limit($r->description, 160) : ''))->implode("\n");

        // Pages secondaires : tout le texte lisible des blocs
        $pages = collect(PageSchema::normalizePages($d['pages'] ?? []))->map(function ($p) {
            $txt = collect($p['blocks'] ?? [])->map(function ($bl) {
                $parts = array_filter([$bl['title'] ?? null, $bl['intro'] ?? null, $bl['body'] ?? null, $bl['text'] ?? null]);
                foreach ((array) ($bl['items'] ?? []) as $it) {
                    if (! is_array($it)) {
                        continue;
                    }
                    $parts[] = match ($bl['type']) {
                        'faq'          => 'Q: '.($it['q'] ?? '').' R: '.($it['a'] ?? ''),
                        'pricing'      => ($it['name'] ?? '').' : '.($it['price'] ?? '').' — '.($it['desc'] ?? '').(! empty($it['features']) ? ' ('.implode(', ', (array) $it['features']).')' : ''),
                        'team'         => ($it['name'] ?? '').' ('.($it['role'] ?? '').') '.($it['bio'] ?? ''),
                        'testimonials' => '« '.($it['text'] ?? '').' » — '.($it['author'] ?? ''),
                        'stats'        => ($it['v'] ?? '').' '.($it['l'] ?? ''),
                        default        => ($it['title'] ?? '').' : '.($it['desc'] ?? ''),
                    };
                }

                return implode(' | ', array_filter(array_map(fn ($s) => trim((string) $s), $parts)));
            })->filter()->implode("\n   ");

            return '• Page « '.$p['title'].' » (/'.$p['slug'].'/) : '.$txt;
        })->implode("\n");

        $sections = array_filter([
            '#services' => 'Services / prestations',
            '#carte'    => ($on('menu') && $menu) ? 'Carte / menu' : null,
            '#chambres' => ($on('rooms') && $rooms) ? 'Chambres' : null,
            '#apropos'  => 'À propos',
            '#avis'     => 'Avis Google',
            '#reserver' => ($on('booking') || $on('restaurant') || $on('rooms')) ? 'Réservation / demande en ligne' : null,
            '#contact'  => 'Contact, horaires, plan',
        ]);
        $pay = $mod('payment');
        $booking = array_filter([
            $on('restaurant') ? 'réservation de table en ligne (créneaux, confirmation par email'.(! empty($mod('restaurant')['sms_confirm']) ? ' et SMS' : '').')' : null,
            $on('rooms') ? 'demande de séjour en ligne (dates, chambre, estimation du prix)' : null,
            $on('booking') && ! $on('restaurant') && ! $on('rooms') ? 'formulaire de demande / réservation sur le site' : null,
            $on('payment') ? (($pay['type'] ?? 'deposit') === 'deposit' ? 'acompte de '.($pay['deposit_percent'] ?? 30).' % demandé en ligne pour les séjours' : 'empreinte bancaire anti no-show pour les tables') : null,
            $on('whatsapp') && ! empty($mod('whatsapp')['number']) ? 'contact WhatsApp possible (bouton sur le site)' : null,
            $on('zenchef') ? 'réservation via ZenChef sur le site' : null,
        ]);

        return collect([
            'Établissement' => ($b['name'] ?? $site->name).($site->sector ? ' ('.$site->sector.')' : ''),
            'Accroche' => trim(($c['tagline'] ?? '').' — '.($c['hero_title'] ?? '').' '.($c['hero_subtitle'] ?? ''), " —"),
            'Points forts' => implode(', ', array_filter((array) ($c['badges'] ?? []), 'is_string')),
            'Chiffres clés' => collect($c['stats'] ?? [])->filter(fn ($x) => is_array($x))->map(fn ($s) => ($s['v'] ?? '').' '.($s['l'] ?? ''))->implode(', '),
            'Adresse' => $b['address'] ?? null,
            'Téléphone' => $b['phone'] ?? null,
            'Email' => $b['email'] ?? null,
            'Site / réseaux' => $b['website'] ?? null,
            'Horaires' => implode(' ; ', (array) ($b['opening_hours'] ?? [])),
            'État actuel' => $this->openState((array) ($b['opening_hours'] ?? [])),
            'Note Google' => isset($b['rating']) ? $b['rating'].'/5 ('.($b['reviews_count'] ?? 0).' avis)' : null,
            'Extraits d\'avis' => collect($b['reviews'] ?? [])->filter(fn ($x) => is_array($x))->take(4)->map(fn ($r) => '« '.Str::limit($r['text'] ?? '', 160).' » ('.($r['author'] ?? '').', '.($r['rating'] ?? '').'/5)')->implode(' | '),
            'Présentation' => trim(($c['about_title'] ?? '').'. '.($c['about_p1'] ?? '').' '.($c['about_p2'] ?? ''), '. '),
            'Services' => collect($c['services'] ?? [])->filter(fn ($x) => is_array($x))->map(fn ($s) => ($s['name'] ?? '').' : '.($s['desc'] ?? '').(! empty($s['price']) ? ' ('.$s['price'].')' : ''))->implode(' | '),
            'Déroulé / méthode' => collect($c['process'] ?? [])->filter(fn ($x) => is_array($x))->map(fn ($s) => ($s['title'] ?? '').' : '.($s['desc'] ?? ''))->implode(' | '),
            'FAQ du site' => collect($c['faq'] ?? [])->filter(fn ($x) => is_array($x))->map(fn ($f) => 'Q: '.($f['q'] ?? '').' R: '.($f['a'] ?? ''))->implode(' | '),
            'Carte / menu' => $menu ?: null,
            'Chambres' => $rooms ?: null,
            'Réservation et contact en ligne' => $booking ? implode(' ; ', $booking) : 'pas de réservation en ligne : orienter vers le téléphone',
            'Autres pages du site' => $pages ?: null,
            'Sections disponibles (ancres)' => collect($sections)->map(fn ($l, $a) => "$a = $l")->implode(', '),
            'Infos pratiques (propriétaire)' => trim((string) $cfg['practical']) ?: null,
            'Questions/réponses complémentaires (propriétaire)' => trim((string) $cfg['faq']) ?: null,
        ])->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->map(fn ($v, $k) => "$k : $v")->implode("\n");
    }

    /** « ouvert, ferme à 22h30 » / « fermé, ouvre à 12h00 » à partir des horaires Google (texte français). */
    private function openState(array $hours): ?string
    {
        if (! $hours) {
            return null;
        }
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $now = now();
        $today = $days[(int) $now->format('w')];
        $line = collect($hours)->first(fn ($h) => Str::startsWith(mb_strtolower((string) $h), $today));
        if (! $line) {
            return null;
        }
        $txt = Str::after($line, ':');
        if (preg_match('/24\s*h/i', $txt)) {
            return 'ouvert 24h/24 aujourd\'hui';
        }
        preg_match_all('/(\d{1,2})[:h](\d{2})\s*[–—-]\s*(\d{1,2})[:h](\d{2})/u', $txt, $m, PREG_SET_ORDER);
        if (! $m) {
            return 'fermé aujourd\'hui ('.trim($txt).')';
        }
        $cur = (int) $now->format('G') * 60 + (int) $now->format('i');
        $fmt = fn (int $v) => sprintf('%02dh%02d', intdiv($v % 1440, 60), $v % 60);
        $ranges = array_map(fn ($r) => [(int) $r[1] * 60 + (int) $r[2], (int) $r[3] * 60 + (int) $r[4]], $m);
        foreach ($ranges as [$a, $b]) {
            $bb = $b <= $a ? $b + 1440 : $b;
            if ($cur >= $a && $cur < $bb) {
                return 'ouvert en ce moment, ferme à '.$fmt($b);
            }
        }
        foreach ($ranges as [$a]) {
            if ($a > $cur) {
                return 'fermé en ce moment, ouvre à '.$fmt($a).' aujourd\'hui';
            }
        }

        return 'fermé pour aujourd\'hui (horaires du jour : '.trim($txt).')';
    }
}
