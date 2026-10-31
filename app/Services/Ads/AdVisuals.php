<?php

namespace App\Services\Ads;

use App\Models\Site;
use App\Services\AiCredits;
use App\Services\Gemini;
use App\Services\ImageStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Visuels publicitaires générés par l'IA aux formats Meta (carré 1:1, portrait 4:5, story 9:16).
 * Image sans texte : l'accroche, l'offre et la note sont posées ensuite par les modèles de mise en page.
 * Quota mensuel inclus dans Joow Ads, puis crédits IA (même portefeuille que le Studio).
 */
class AdVisuals
{
    public const FORMATS = ['square' => '1:1', 'portrait' => '4:5', 'story' => '9:16'];

    public const CREDIT_COST = 2;

    public static function included(): int
    {
        return (int) config('joow.ads_visuals_per_month', 20);
    }

    public function usage(Site $site): array
    {
        $u = (array) ($site->site_data['addons']['ads']['usage'] ?? []);
        $used = ($u['month'] ?? null) === now()->format('Y-m') ? (int) ($u['used'] ?? 0) : 0;

        return ['included' => self::included(), 'used' => $used, 'left' => max(0, self::included() - $used), 'credit_cost' => self::CREDIT_COST];
    }

    public function gallery(Site $site): array
    {
        return array_reverse(array_slice((array) ($site->site_data['addons']['ads']['visuals'] ?? []), -40));
    }

    /** Mode de paiement d'un nouveau visuel (quota mensuel, crédits IA) ou erreur si impossible. */
    public function payment(Site $site, bool $admin = false): array
    {
        $usage = $this->usage($site);
        $credits = app(AiCredits::class);
        $credits->refresh($site);
        $payWith = $admin ? 'admin' : ($usage['left'] > 0 ? 'quota' : 'credits');
        if ($payWith === 'credits' && $credits->balance($site) < self::CREDIT_COST) {
            return ['error' => 'Vos '.self::included().' visuels du mois sont utilisés. Il faut '.self::CREDIT_COST.' crédits IA par visuel supplémentaire : rechargez vos crédits depuis le Studio.', 'status' => 402];
        }

        return ['pay_with' => $payWith];
    }

    /** Décompte du visuel produit et ajout à la galerie. */
    public function commit(Site $site, string $payWith, array $entry): void
    {
        $site->refresh();
        $d = $site->site_data ?? [];
        if ($payWith === 'quota') {
            $u = $this->usage($site);
            $d['addons']['ads']['usage'] = ['month' => now()->format('Y-m'), 'used' => $u['used'] + 1];
        }
        $d['addons']['ads']['visuals'] = array_slice(array_merge((array) ($d['addons']['ads']['visuals'] ?? []), [$entry + ['at' => now()->toIso8601String()]]), -60);
        $site->forceFill(['site_data' => $d])->save();
        if ($payWith === 'credits') {
            app(AiCredits::class)->charge($site->fresh(), self::CREDIT_COST, ['message' => 'Visuel publicitaire IA', 'applied' => [$entry['brief'] ?? 'Visuel']]);
        }
    }

    /**
     * @return array{url: string, format: string, paid_with: string}|array{error: string, status: int}
     */
    public function generate(Site $site, string $brief, string $format = 'square', ?string $refUrl = null, bool $admin = false): array
    {
        $aspect = self::FORMATS[$format] ?? '1:1';
        $usage = $this->usage($site);
        $credits = app(AiCredits::class);
        $credits->refresh($site);
        $payWith = $admin || $usage['left'] > 0 ? 'quota' : 'credits';
        if ($payWith === 'credits' && $credits->balance($site) < self::CREDIT_COST) {
            return ['error' => 'Vos '.self::included().' visuels du mois sont utilisés. Il faut '.self::CREDIT_COST.' crédits IA par visuel supplémentaire : rechargez vos crédits depuis le Studio.', 'status' => 402];
        }

        $label = config("sectors.{$site->sector}.label") ?? $site->sector;
        $prompt = "Photographie publicitaire haut de gamme pour une publicité Facebook / Instagram d'un professionnel local ({$label}"
            .($site->city ? ", {$site->city}" : '').', « '.$site->name." »).\nSujet : {$brief}\n"
            ."Style : photo réaliste, lumineuse, chaleureuse et crédible, couleurs naturelles, mise au point nette, cadrage magazine. "
            ."Composition qui laisse une zone calme et dégagée en haut et en bas de l'image pour y poser du texte. "
            .'Format '.$aspect.'. IMPORTANT : aucun texte, aucune lettre, aucun chiffre, aucun logo, aucune marque, aucun filigrane dans l\'image.'
            .($refUrl ? "\nInspire-toi de la photo jointe (lieu, ambiance, matériel réels de l'établissement) sans la recopier à l'identique." : '');

        $ref = null;
        if ($refUrl) {
            try {
                $r = Http::timeout(15)->get($refUrl);
                $ref = $r->successful() ? $r->body() : null;
            } catch (\Throwable) {
            }
        }
        $bytes = Gemini::image($prompt, $aspect, $ref, 'AdVisuals');
        if (! $bytes) {
            return ['error' => 'La génération du visuel n\'a pas abouti. Réessayez dans un instant (rien n\'a été décompté).', 'status' => 502];
        }
        $name = app(ImageStore::class)->storeBytes($bytes, $site->slug);
        if (! $name) {
            return ['error' => 'Image illisible, réessayez.', 'status' => 502];
        }
        $url = rtrim(config('app.url'), '/')."/media/{$site->slug}/$name";

        $site->refresh();
        $d = $site->site_data ?? [];
        if ($payWith === 'quota' && ! $admin) {
            $d['addons']['ads']['usage'] = ['month' => now()->format('Y-m'), 'used' => $usage['used'] + 1];
        }
        $d['addons']['ads']['visuals'] = array_slice(array_merge((array) ($d['addons']['ads']['visuals'] ?? []), [['url' => $url, 'format' => $format, 'brief' => mb_substr($brief, 0, 200), 'at' => now()->toIso8601String()]]), -60);
        $site->forceFill(['site_data' => $d])->save();
        if ($payWith === 'credits') {
            $credits->charge($site->fresh(), self::CREDIT_COST, ['message' => 'Visuel publicitaire IA ('.$aspect.')', 'applied' => ['Visuel '.$aspect]]);
        }
        Log::info("Joow Ads {$site->slug} : visuel IA $aspect ($payWith)");

        return ['url' => $url, 'format' => $format, 'paid_with' => $payWith];
    }
}
