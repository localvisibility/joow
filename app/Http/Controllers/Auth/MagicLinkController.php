<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Connexion sans mot de passe : un lien signé (30 min) envoyé par email.
 * Crée le compte à la volée si l'email est inconnu — zéro friction pour un
 * client qui vient de générer son site.
 */
class MagicLinkController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower(trim($data['email']));

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => Str::title(Str::before($email, '@')), 'password' => bcrypt(Str::random(40)), 'email_verified_at' => now()]
        );

        $link = URL::temporarySignedRoute('magic.login', now()->addMinutes(30), ['user' => $user->id, 'to' => $request->input('redirect')]);

        Mail::send('emails.notification', [
            'title' => 'Votre lien de connexion Joow',
            'intro' => "Cliquez sur le bouton ci-dessous pour vous connecter à votre espace Joow. Ce lien est valable 30 minutes et ne peut être utilisé qu'une fois.",
            'rows'  => [],
            'cta'   => ['label' => 'Me connecter', 'url' => $link],
            'note'  => "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.",
            'brand' => ['name' => 'Joow', 'accent' => '#6366f1', 'url' => 'https://app.joow.fr', 'phone' => null, 'address' => null, 'email' => null],
        ], function ($m) use ($email) {
            $m->to($email)->subject('Votre lien de connexion Joow');
        });

        return back()->with('status', "Lien envoyé à $email. Regardez votre boîte mail (et les indésirables).");
    }

    /** Route signée : connecte et rattache les sites créés sans compte. */
    public function login(Request $request, User $user)
    {
        $guestSlugs = $request->session()->get('joow_sites', []);
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        Site::claimFor($user, $guestSlugs);

        $to = (string) $request->query('to');
        if ($to && Str::startsWith($to, '/') && ! Str::startsWith($to, '//')) {
            return redirect($to);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
