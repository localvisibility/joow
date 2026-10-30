<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Garde-fou contre la création en série d'aperçus gratuits (agences, curieux).
 * - Au plus N sites par 24 h pour un même appareil (cookie), un même email ou un même compte.
 * - L'adresse IP seule ne bloque qu'au-delà de 3 × N : en 4G (CGNAT), dans une entreprise ou un
 *   coworking, des inconnus partagent la même IP et ne doivent pas se bloquer entre eux.
 * Les clients payants et l'équipe Joow ne sont pas limités.
 */
class CreationGuard
{
    public static function limit(): int
    {
        return (int) config('joow.free_sites_per_day', 5);
    }

    public const COOKIE = 'joow_did';

    /** Identifiant d'appareil (cookie 2 ans), créé au besoin. */
    public static function device(Request $request): string
    {
        $id = (string) $request->cookie(self::COOKIE);
        if (! preg_match('/^[a-z0-9]{32}$/', $id)) {
            $id = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(32));
            \Illuminate\Support\Facades\Cookie::queue(self::COOKIE, $id, 60 * 24 * 730, '/', null, true, true, false, 'Lax');
            $request->cookies->set(self::COOKIE, $id);
        }

        return $id;
    }

    /** Message à afficher si la limite est atteinte, sinon null. */
    public function check(Request $request, ?string $email = null): ?string
    {
        $user = $request->user();
        if ($user && ($user->isAdmin() || Site::where('user_id', $user->id)->whereIn('status', ['paid', 'published'])->exists())) {
            return null;
        }
        $email = strtolower(trim((string) ($email ?: $user?->email)));
        $device = self::device($request);
        $recent = fn () => Site::where('created_at', '>=', now()->subDay());

        // IP privée / interne (proxy mal configuré : tout le monde aurait la même) : jamais utilisée pour bloquer
        $ip = (string) $request->ip();
        $publicIp = (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $byIp = $publicIp ? $recent()->where('creator_ip', $ip)->count() : 0;
        $count = $recent()
            ->where(function ($q) use ($device, $email, $user) {
                $q->where('creator_device', $device);
                if ($email !== '') {
                    $q->orWhereRaw('lower(owner_email) = ?', [$email]);
                }
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })->count();

        $blocked = $count >= self::limit() ? 'device_email' : ($byIp >= 3 * self::limit() ? 'ip' : null);
        if ($blocked) {
            try {
                \Illuminate\Support\Facades\DB::table('creation_blocks')->insert([
                    'ip' => $ip, 'device' => $device, 'email' => $email ?: null, 'reason' => $blocked,
                    'count' => $blocked === 'ip' ? $byIp : $count,
                    'flow' => match (true) { $request->is('creer') => 'questionnaire', $request->is('refonte') => 'refonte', default => 'google' },
                    'query' => mb_substr((string) ($request->input('query') ?? $request->input('name') ?? $request->input('url') ?? ''), 0, 250),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Throwable) {
            }
        }

        return $blocked
            ? 'Vous avez déjà créé '.self::limit().' sites aujourd\'hui. Retrouvez-les dans votre espace, mettez-en un en ligne, ou revenez demain pour en créer d\'autres.'
            : null;
    }

    /** Aperçu déjà créé pour cette fiche Google par la même personne (IP, session ou email) : on le réutilise. */
    public function existing(Request $request, ?string $placeId, ?string $email = null): ?Site
    {
        if (! $placeId) {
            return null;
        }
        $guest = (array) $request->session()->get('joow_sites', []);
        $email = strtolower(trim((string) ($email ?: $request->user()?->email)));

        return Site::where('place_id', $placeId)->where('status', '!=', 'failed')->where('created_at', '>=', now()->subDays(30))
            ->where(function ($q) use ($request, $guest, $email) {
                $q->where('creator_device', self::device($request));
                if ($guest) {
                    $q->orWhereIn('slug', $guest);
                }
                if ($email !== '') {
                    $q->orWhereRaw('lower(owner_email) = ?', [$email]);
                }
            })->latest()->first();
    }
}
