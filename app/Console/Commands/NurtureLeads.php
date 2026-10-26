<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Accounts;
use Illuminate\Console\Command;

/**
 * Relance douce des personnes qui ont généré un site sans le mettre en ligne : J+1, J+3, J+6.
 * Jamais après désinscription, jamais si un site est déjà payé. Activation : NURTURE_ENABLED=true.
 */
class NurtureLeads extends Command
{
    protected $signature = 'emails:nurture {--dry-run : Lister sans envoyer} {--force : Ignorer NURTURE_ENABLED}';

    protected $description = 'Envoie les emails de relance J+1 / J+3 / J+6 aux comptes dont le site n\'est pas encore en ligne';

    /** [étape => [jours, sujet, titre, intro, bouton]] */
    public const STEPS = [
        'j1' => [1, 'Trois choses à faire sur votre site aujourd\'hui', 'Votre site vous attend dans le Studio',
            "Hier, votre site a été généré. Voici les trois retouches qui font la différence, chacune prend moins de deux minutes :\n\n1. Vérifiez votre numéro de téléphone et vos horaires : ce sont les deux informations que vos clients cherchent en premier.\n2. Remplacez une photo par une des vôtres si vous en avez une meilleure : un clic sur l'image dans le Studio.\n3. Relisez la section « Prestations » et corrigez un prix ou un libellé si besoin.\n\nTout se modifie directement sur la page, sans compétence technique.",
            'Ouvrir mon Studio'],
        'j3' => [3, 'Réservation, devis, assistant IA : vos modules sont prêts', 'Faites travailler votre site',
            "Votre site peut déjà faire plus qu'afficher vos informations :\n\n• Réservation ou prise de rendez-vous en ligne, avec rappel automatique.\n• Demande de devis en trois étapes, reçue par email.\n• Assistant IA qui répond à vos clients 24h/24 à partir de vos informations.\n• Vos avis Google mis en avant.\n\nDans le Studio, onglet « Modules », tout s'active en un clic. Et l'assistant IA peut vous créer une page « Tarifs » ou « Réalisations » sur simple demande.",
            'Activer mes modules'],
        'j6' => [6, 'Dernier coup de pouce pour votre site', 'On le met en ligne ?',
            "Votre site est prêt depuis presque une semaine. Le mettre en ligne prend une minute : vous choisissez la formule, et il est accessible immédiatement sur votre adresse joow.fr, avec un nom de domaine personnalisé possible.\n\nSi quelque chose vous retient (un texte, une photo, une question), répondez simplement à cet email : on vous aide.",
            'Mettre mon site en ligne'],
    ];

    public function handle(Accounts $accounts): int
    {
        if (! config('services.nurture.enabled') && ! $this->option('force')) {
            $this->info('Relances désactivées (NURTURE_ENABLED=false).');

            return self::SUCCESS;
        }
        $sent = 0;
        $users = User::whereNull('marketing_optout_at')
            ->whereHas('sites', fn ($q) => $q->where('status', 'preview'))
            ->whereDoesntHave('sites', fn ($q) => $q->whereIn('status', ['paid', 'published']))
            ->where('created_at', '<=', now()->subDay())->get();

        foreach ($users as $user) {
            $site = $user->sites()->where('status', 'preview')->latest('created_at')->first();
            if (! $site) {
                continue;
            }
            $done = (array) ($user->nurture ?? []);
            foreach (self::STEPS as $step => [$days, $subject, $title, $intro, $button]) {
                if (isset($done[$step]) || $user->created_at->gt(now()->subDays($days))) {
                    continue;
                }
                $this->line(($this->option('dry-run') ? '[dry-run] ' : '')."{$user->email} → $step ({$site->slug})");
                if (! $this->option('dry-run')) {
                    $to = $step === 'j6' ? route('public.site', $site->slug, absolute: false) : route('sites.editor', $site->slug, absolute: false);
                    $accounts->send($user, $subject, [
                        'title' => $title,
                        'intro' => "Bonjour {$user->name},\n\n".$intro,
                        'rows'  => [['Votre site', 'https://'.$site->slug.'.joow.fr']],
                        'cta'   => ['label' => $button, 'url' => $accounts->magicLink($user, $to, 7)],
                        'note'  => 'Pour ne plus recevoir ces conseils : '.$accounts->unsubscribeLink($user),
                    ]);
                    $done[$step] = now()->toDateString();
                    $user->forceFill(['nurture' => $done])->save();
                    $sent++;
                }
                break; // une seule relance par jour et par personne
            }
        }
        $this->info("$sent relance(s) envoyée(s).");

        return self::SUCCESS;
    }
}
