<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Compte créé automatiquement dès qu'un email est laissé (page d'attente, site prêt, questionnaire,
 * refonte, Studio) : sans mot de passe, rattaché aux sites de la session. L'accès se fait par lien
 * magique, par Google, ou par un mot de passe que la personne choisit depuis l'email de bienvenue.
 */
class Accounts
{
    /** Trouve ou crée le compte, rattache les sites (même email ou même session). Retourne [user, créé ?]. */
    public function ensure(string $email, array $guestSlugs = [], string $source = 'site', ?string $name = null): array
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        $created = false;
        if (! $user) {
            $user = User::create([
                'name'     => $name ?: Str::title(Str::before($email, '@')),
                'email'    => $email,
                'password' => bcrypt(Str::random(40)),
            ]);
            $user->forceFill(['source' => $source])->save();
            $created = true;
        }
        Site::claimFor($user, $guestSlugs);

        return [$user, $created];
    }

    /** Email de bienvenue : récap du site, Studio en un clic (lien magique), choix du mot de passe. */
    public function welcome(User $user, Site $site): void
    {
        $studio = $this->magicLink($user, route('sites.editor', $site->slug, absolute: false), days: 7);
        $setPassword = $this->setPasswordLink($user);
        $b = $site->site_data['business'] ?? [];
        $rows = array_values(array_filter([
            ['Votre site', 'https://'.$site->slug.'.joow.fr'],
            ! empty($b['rating']) ? ['Note Google reprise', $b['rating'].'/5 · '.($b['reviews_count'] ?? 0).' avis'] : null,
            ['Votre Studio', $studio],
        ]));

        $this->send($user, 'Votre site '.$site->name.' est prêt — ouvrez votre Studio', [
            'title' => 'Votre site est prêt, et il est déjà à vous',
            'intro' => "Bonjour {$user->name},\n\nVotre site a été généré et un espace Joow a été créé avec cette adresse. Vous pouvez tout modifier en quelques clics dans le Studio : textes, photos, couleurs, pages, et activer la réservation, les devis en ligne, l'assistant IA… C'est gratuit tant que le site n'est pas mis en ligne.",
            'rows'  => $rows,
            'cta'   => ['label' => 'Ouvrir mon Studio', 'url' => $studio],
            'cta2'  => ['label' => 'Choisir mon mot de passe', 'url' => $setPassword],
            'note'  => "Le bouton « Ouvrir mon Studio » vous connecte directement pendant 7 jours. Ensuite, connectez-vous avec Google, par lien envoyé par email, ou avec le mot de passe que vous aurez choisi.\n\nNous vous enverrons quelques conseils pour tirer le meilleur de votre site. Pour ne plus les recevoir : ".$this->unsubscribeLink($user),
        ]);
    }

    /** Lien magique signé vers une page précise (connexion + rattachement des sites de la session). */
    public function magicLink(User $user, string $to = '/dashboard', int $days = 7): string
    {
        return URL::temporarySignedRoute('magic.login', now()->addDays($days), ['user' => $user->id, 'to' => $to]);
    }

    /** Page « choisir mon mot de passe » (jeton de réinitialisation Laravel, 60 min par défaut). */
    public function setPasswordLink(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('password.reset', ['token' => $token]).'?'.http_build_query(['email' => $user->email, 'set' => 1]);
    }

    public function unsubscribeLink(User $user): string
    {
        return URL::signedRoute('emails.unsubscribe', ['user' => $user->id]);
    }

    /** Envoi d'un email Joow (gabarit de notification, marque Joow). */
    public function send(User $user, string $subject, array $data): void
    {
        try {
            Mail::to($user->email, $user->name)->send(new \App\Mail\JoowNotification($subject, $data));
        } catch (\Throwable $e) {
            Log::warning('Email compte : '.$e->getMessage());
        }
    }
}
