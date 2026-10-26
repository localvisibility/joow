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
            // Conversion « Lead » (compte créé) : pixel + API de conversions, même event_id
            try {
                app(MetaConversions::class)->event('Lead', ['content_name' => 'compte', 'source' => $source], $email, $user->id);
                app(MetaConversions::class)->event('CompleteRegistration', ['status' => true, 'source' => $source], $email, $user->id);
            } catch (\Throwable $e) {
                Log::info('Tracking Lead : '.$e->getMessage());
            }
        }
        Site::claimFor($user, $guestSlugs);

        return [$user, $created];
    }

    /**
     * Email « votre site est prêt » : visuel du site, ce qu'on peut faire, Studio en un clic (lien magique),
     * choix du mot de passe. Envoyé une seule fois par site, quel que soit le canal (email, Google, Studio).
     */
    public function welcome(User $user, Site $site, bool $force = false): bool
    {
        if (! $force && ! empty($site->site_data['emails']['welcome_at'])) {
            return false;
        }
        if (! in_array($site->status, ['preview', 'paid', 'published'], true)) {
            return false; // pas encore généré : le job enverra à la fin
        }
        $studio = $this->magicLink($user, route('sites.editor', $site->slug, absolute: false), days: 7);
        $b = $site->site_data['business'] ?? [];
        $first = Str::before($user->name, ' ');
        $rows = array_values(array_filter([
            ['Votre site', 'https://'.$site->slug.'.joow.fr'],
            ! empty($b['rating']) ? ['Avis Google repris', $b['rating'].'/5 · '.($b['reviews_count'] ?? 0).' avis'] : null,
            ['Mise en ligne', 'Quand vous voulez · 39 € HT/mois sans engagement, ou 349 € HT une fois'],
        ]));

        $this->send($user, $site->name.' : votre site est prêt 🎉', [
            'preheader' => 'Ouvrez votre Studio en un clic et faites-en votre site.',
            'title' => 'Votre site est prêt, '.$first,
            'image' => $site->site_data['images']['hero'] ?? null,
            'intro' => "Il a été construit à partir de vos informations réelles : vos prestations, vos photos, vos avis. Il est déjà à vous, et tout se modifie directement sur la page, sans aucune compétence technique.",
            'bullets' => [
                ['✏️', 'Modifiez en quelques clics', 'Cliquez un texte, une photo ou une couleur dans le Studio : c\'est changé.'],
                ['🗓️', 'Activez vos modules', 'Réservation, rendez-vous ou devis en ligne, avis Google, formulaire intelligent, en un clic.'],
                ['🤖', 'Demandez à l\'assistant IA', '« Ajoute une page Tarifs », « rends le ton plus chaleureux » : il le fait pour vous.'],
                ['🚀', 'Mettez en ligne quand c\'est parfait', 'Gratuit jusque-là. Ensuite, votre adresse à vous, un vrai site qui travaille 24h/24.'],
            ],
            'rows'  => $rows,
            'cta'   => ['label' => 'Ouvrir mon Studio', 'url' => $studio],
            'cta2'  => ['label' => 'Choisir mon mot de passe', 'url' => $this->setPasswordLink($user)],
            'note'  => "Le bouton « Ouvrir mon Studio » vous connecte directement pendant 7 jours. Ensuite : Google, lien envoyé par email, ou le mot de passe que vous aurez choisi.\nUne question ? Répondez simplement à cet email.\n\nNous vous enverrons quelques conseils pour tirer le meilleur de votre site. Pour ne plus les recevoir : ".$this->unsubscribeLink($user),
        ]);
        $this->mark($site, 'welcome_at');

        return true;
    }

    /** Après une connexion (Google, lien, mot de passe) : le dernier site généré reçoit son email de bienvenue s'il ne l'a pas eu. */
    public function afterLogin(User $user): void
    {
        $site = $user->sites()->whereIn('status', ['preview', 'paid', 'published'])->latest('created_at')->first();
        if ($site) {
            $this->welcome($user, $site);
        }
    }

    /** Paiement commencé (page Stripe ouverte) : mémorisé pour relancer si abandon. */
    public function checkoutStarted(Site $site, string $plan): void
    {
        $this->mark($site, 'checkout_started_at', ['checkout_plan' => $plan]);
    }

    /** Site mis en ligne : félicitations et prochaines étapes qui font décoller la visibilité. */
    public function live(User $user, Site $site, string $plan): bool
    {
        if (! empty($site->site_data['emails']['live_at'])) {
            return false;
        }
        $url = 'https://'.$site->slug.'.joow.fr';
        $space = $this->magicLink($user, '/dashboard', days: 7);
        $this->send($user, $site->name.' est en ligne 🎉', [
            'preheader' => 'Votre site est accessible à tous. Voici les 4 gestes qui font la différence.',
            'title' => 'Félicitations, votre site est en ligne',
            'image' => $site->site_data['images']['hero'] ?? null,
            'intro' => "Il est accessible dès maintenant à l'adresse ci-dessous, sur mobile comme sur ordinateur. Voici ce que font nos clients les plus visibles dans la semaine qui suit :",
            'bullets' => [
                ['📍', 'Ajoutez-le à votre fiche Google', 'Dans votre profil Google Business, champ « Site web » : vos clients y arrivent depuis Maps.'],
                ['🌐', 'Votre nom de domaine', 'Depuis « Modules », reliez un domaine existant ou commandez-en un en deux minutes.'],
                ['📣', 'Partagez-le', 'Signature d\'email, Instagram, Facebook, WhatsApp : un lien suffit.'],
                ['💬', 'Laissez l\'assistant répondre', 'Horaires, tarifs, prise de contact : il répond à vos clients 24h/24.'],
            ],
            'rows'  => [['Votre adresse', $url], ['Formule', $plan === 'liberte' ? 'Liberté · paiement unique' : 'Pro · mensuel, sans engagement'], ['Crédits IA inclus', '100 pour l\'assistant du Studio']],
            'cta'   => ['label' => 'Ouvrir mon espace', 'url' => $space],
            'cta2'  => ['label' => 'Voir mon site', 'url' => $url],
            'note'  => 'Vos demandes, réservations et statistiques arrivent dans votre espace. Une question ? Répondez simplement à cet email.',
        ]);
        $this->mark($site, 'live_at');

        return true;
    }

    /** Horodatage dans site_data.emails (une seule fois par type d'email et par site). */
    public function mark(Site $site, string $key, array $extra = []): void
    {
        $d = $site->site_data ?? [];
        $d['emails'] = array_merge((array) ($d['emails'] ?? []), [$key => now()->toIso8601String()], $extra);
        $site->update(['site_data' => $d]);
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
