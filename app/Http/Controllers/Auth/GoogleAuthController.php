<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/** « Continuer avec Google » (OAuth 2.0 via Socialite). */
class GoogleAuthController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('services.google.client_id') && (bool) config('services.google.client_secret');
    }

    public function redirect(Request $request)
    {
        abort_unless(self::enabled(), 404);
        if ($to = $request->query('redirect')) {
            $request->session()->put('url.intended', $to);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);
        try {
            $g = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Connexion Google interrompue. Réessayez.']);
        }

        $email = strtolower((string) $g->getEmail());
        abort_if($email === '', 422, 'Google n\'a pas transmis d\'adresse email.');

        $user = User::where('google_id', $g->getId())->orWhere('email', $email)->first();
        if (! $user) {
            $user = User::create(['name' => $g->getName() ?: Str::before($email, '@'), 'email' => $email, 'password' => bcrypt(Str::random(40)), 'email_verified_at' => now()]);
        }
        $user->forceFill(['google_id' => $g->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $guestSlugs = $request->session()->get('joow_sites', []);
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        Site::claimFor($user, $guestSlugs);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
