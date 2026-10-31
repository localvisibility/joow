<?php

namespace App\Services\Ads;

use App\Models\LandingPage;
use Illuminate\Support\Facades\DB;

/**
 * Résultats A/B d'une page d'offre : vues, conversions, taux, et verdict statistique
 * (test de deux proportions ; « gagnant » à 95 % de confiance, au moins 100 vues par variante).
 */
class AbTest
{
    public const MIN_VIEWS = 100;

    public function results(LandingPage $lp): array
    {
        $rows = DB::table('landing_stats')->where('landing_id', $lp->id)
            ->selectRaw('variant, sum(views) as views, sum(conversions) as conversions, sum(calls) as calls')->groupBy('variant')->get()->keyBy('variant');

        $out = [];
        foreach ((array) $lp->variants as $v) {
            $k = $v['key'];
            $r = $rows[$k] ?? null;
            $views = (int) ($r->views ?? 0);
            $conv = (int) ($r->conversions ?? 0);
            $out[$k] = ['views' => $views, 'conversions' => $conv, 'calls' => (int) ($r->calls ?? 0), 'rate' => $views ? round($conv / $views * 100, 1) : 0.0];
        }

        return ['variants' => $out, 'verdict' => $this->verdict($out)];
    }

    /** ['status' => collecting|tie|winner, 'winner' => 'A', 'confidence' => 97, 'uplift' => 42] */
    public function verdict(array $v): array
    {
        if (count($v) < 2) {
            return ['status' => 'single'];
        }
        $keys = array_keys($v);
        [$a, $b] = [$v[$keys[0]], $v[$keys[1]]];
        if ($a['views'] < self::MIN_VIEWS || $b['views'] < self::MIN_VIEWS) {
            return ['status' => 'collecting', 'needed' => self::MIN_VIEWS];
        }
        $p1 = $a['conversions'] / $a['views'];
        $p2 = $b['conversions'] / $b['views'];
        $p = ($a['conversions'] + $b['conversions']) / ($a['views'] + $b['views']);
        $se = sqrt($p * (1 - $p) * (1 / $a['views'] + 1 / $b['views']));
        if ($se == 0.0) {
            return ['status' => 'tie', 'confidence' => 0];
        }
        $z = abs($p1 - $p2) / $se;
        $confidence = (int) round((1 - 2 * (1 - $this->phi($z))) * 100);
        $best = $p1 >= $p2 ? $keys[0] : $keys[1];
        $low = min($p1, $p2);
        $uplift = $low > 0 ? (int) round((max($p1, $p2) - $low) / $low * 100) : null;

        return $confidence >= 95
            ? ['status' => 'winner', 'winner' => $best, 'confidence' => $confidence, 'uplift' => $uplift]
            : ['status' => 'tie', 'leader' => $best, 'confidence' => $confidence, 'uplift' => $uplift];
    }

    /** Fonction de répartition de la loi normale (approximation d'Abramowitz-Stegun). */
    private function phi(float $z): float
    {
        $t = 1 / (1 + 0.2316419 * $z);
        $d = 0.3989423 * exp(-$z * $z / 2);

        return 1 - $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
    }

    /** Comptage d'une vue / conversion (appel = conversion « téléphone »). */
    public static function hit(LandingPage $lp, string $variant, string $type): void
    {
        $col = ['view' => 'views', 'conversion' => 'conversions', 'call' => 'calls'][$type] ?? null;
        if (! $col || ! collect($lp->variants)->contains('key', $variant)) {
            return;
        }
        $day = now()->toDateString();
        $q = DB::table('landing_stats')->where(['landing_id' => $lp->id, 'variant' => $variant, 'day' => $day]);
        if (! $q->exists()) {
            try {
                DB::table('landing_stats')->insert(['landing_id' => $lp->id, 'variant' => $variant, 'day' => $day]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            }
        }
        $q->increment($col);
        if ($type === 'call') {
            $q->increment('conversions');
        }
    }
}
