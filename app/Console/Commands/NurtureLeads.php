<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Accounts;
use Illuminate\Console\Command;

/**
 * Programme d'emails de conversion, par site généré et non mis en ligne :
 *  - relances J+1, J+3, J+6, J+10, J+20 après la génération ;
 *  - rappel de paiement abandonné 1 h après l'ouverture de la page Stripe sans paiement.
 * Jamais après désinscription, jamais si la personne a déjà un site en ligne, une relance par jour et par personne.
 * Désactivable avec NURTURE_ENABLED=false.
 */
class NurtureLeads extends Command
{
    protected $signature = 'emails:nurture {--dry-run : Lister sans envoyer} {--force : Ignorer NURTURE_ENABLED}';

    protected $description = 'Relances des sites générés mais pas en ligne, et rappels de paiement abandonné';

    /** étape => [jours après génération, sujet, pré-en-tête, titre, intro, arguments, libellé du bouton, destination] */
    public const STEPS = [
        'j1' => [1, 'Trois retouches de deux minutes pour votre site', 'Les trois détails que vos clients regardent en premier.', 'Votre site vous attend dans le Studio',
            "Hier, votre site a été généré à partir de vos informations. Avant de le montrer, trois retouches de deux minutes chacune font toute la différence :",
            [['📞', 'Téléphone et horaires', 'Ce sont les deux informations que vos clients cherchent en premier. Vérifiez-les en haut de page.'], ['🖼️', 'Une photo à vous', 'Un clic sur une image dans le Studio pour la remplacer par la vôtre.'], ['✅', 'Vos prestations', 'Relisez la section Prestations : un prix, un libellé, et c\'est vous.']],
            'Ouvrir mon Studio', 'studio'],
        'j3' => [3, 'Réservation, devis, assistant IA : vos modules sont prêts', 'Un site qui travaille pour vous, même la nuit.', 'Faites travailler votre site',
            "Votre site peut déjà faire bien plus qu'afficher vos informations. Dans le Studio, onglet « Modules », tout s'active en un clic :",
            [['🗓️', 'Réservation ou rendez-vous en ligne', 'Avec rappel automatique à vos clients.'], ['📝', 'Demande de devis en trois étapes', 'Vous recevez la demande par email, prête à chiffrer.'], ['🤖', 'Assistant IA 24h/24', 'Il répond à vos clients à partir de vos informations, et peut créer une page « Tarifs » sur simple demande.'], ['⭐', 'Vos avis Google en avant', 'Déjà intégrés, mis à jour automatiquement.']],
            'Activer mes modules', 'studio'],
        'j6' => [6, 'On le met en ligne ?', 'Une minute, sans engagement, et votre site existe vraiment.', 'Votre site est prêt depuis une semaine',
            "Le mettre en ligne prend une minute : vous choisissez la formule et il est accessible immédiatement sur votre adresse joow.fr, avec un nom de domaine personnalisé possible.",
            [['🚀', 'Pro · 39 € HT par mois', 'Sans engagement, 100 crédits IA par mois, modules et assistant inclus.'], ['🔒', 'Liberté · 349 € HT une fois', 'Votre site pour toujours, sans abonnement.'], ['💬', 'Quelque chose vous retient ?', 'Un texte, une photo, une question : répondez à cet email, on vous aide.']],
            'Mettre mon site en ligne', 'site'],
        'j10' => [10, 'Ce que votre site change pour vos clients', 'Être trouvé, inspirer confiance, être contacté facilement.', 'Pourquoi un site, en 2026',
            "Trois clients sur quatre regardent un site avant d'appeler un professionnel qu'ils ne connaissent pas. Le vôtre est prêt. Voici ce qu'il change :",
            [['🔎', 'Vous êtes trouvé', 'Votre nom, votre ville, vos prestations : une page claire que Google comprend.'], ['🤝', 'Vous inspirez confiance', 'Vos avis, vos photos, vos horaires au même endroit, sur mobile.'], ['📩', 'On vous contacte plus facilement', 'Appel en un tap, devis ou réservation sans attendre que vous décrochiez.']],
            'Revoir mon site', 'site'],
        'j20' => [20, 'Votre site est toujours là', 'Rien n\'est perdu : il vous attend, tel que vous l\'avez laissé.', 'Toujours là quand vous voulez',
            "Votre site est conservé tel quel dans votre espace Joow. Quand vous aurez un moment, il suffit d'un clic pour reprendre, et d'une minute pour le mettre en ligne.\n\nSi vous préférez qu'on fasse les dernières retouches avec vous, répondez à cet email : on s'en occupe.",
            [],
            'Reprendre mon site', 'studio'],
    ];

    public function handle(Accounts $accounts): int
    {
        if (! config('services.nurture.enabled') && ! $this->option('force')) {
            $this->info('Relances désactivées (NURTURE_ENABLED=false).');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $sent = 0;
        $today = now()->toDateString();

        $sites = Site::whereNotNull('user_id')->where('status', 'preview')->with('user')->orderByDesc('created_at')->get()
            ->filter(fn (Site $s) => $s->user && ! $s->user->marketing_optout_at)
            ->unique('user_id'); // le dernier site de chaque personne

        foreach ($sites as $site) {
            $user = $site->user;
            if (Site::where('user_id', $user->id)->whereIn('status', ['paid', 'published'])->exists()) {
                continue; // déjà client
            }
            $emails = (array) ($site->site_data['emails'] ?? []);
            if (($emails['last_nurture_day'] ?? null) === $today) {
                continue; // une relance par jour
            }

            // 1) Paiement abandonné : priorité, 1 h après l'ouverture de Stripe, une seule fois
            if (! empty($emails['checkout_started_at']) && empty($emails['checkout_reminder_at']) && \Illuminate\Support\Carbon::parse($emails['checkout_started_at'])->lte(now()->subMinutes(60))) {
                $this->line(($dry ? '[dry-run] ' : '')."{$user->email} → paiement abandonné ({$site->slug})");
                if (! $dry) {
                    $plan = ($emails['checkout_plan'] ?? 'pro') === 'liberte' ? 'Liberté · 349 € HT une fois' : 'Pro · 39 € HT par mois, sans engagement';
                    $accounts->send($user, 'Il ne manque qu\'une étape pour mettre '.$site->name.' en ligne', [
                        'preheader' => 'Votre site est prêt, le paiement n\'a pas été finalisé.',
                        'title' => 'Il ne manque qu\'une étape',
                        'image' => $site->site_data['images']['hero'] ?? null,
                        'intro' => "Vous aviez commencé la mise en ligne de votre site, mais le paiement n'a pas été finalisé. Rien n'est perdu : votre site vous attend, et la mise en ligne est immédiate.",
                        'rows' => [['Votre site', 'https://'.$site->slug.'.joow.fr'], ['Formule choisie', $plan]],
                        'cta' => ['label' => 'Finaliser la mise en ligne', 'url' => $accounts->magicLink($user, route('public.site', $site->slug, absolute: false), 7)],
                        'note' => 'Un doute sur la formule ou une question ? Répondez simplement à cet email. Pour ne plus recevoir nos conseils : '.$accounts->unsubscribeLink($user),
                    ]);
                    $accounts->mark($site, 'checkout_reminder_at', ['last_nurture_day' => $today]);
                    $sent++;
                }

                continue;
            }

            // 2) Séquence après génération
            foreach (self::STEPS as $step => [$days, $subject, $preheader, $title, $intro, $bullets, $button, $dest]) {
                if (isset($emails['nurture_'.$step]) || $site->created_at->gt(now()->subDays($days))) {
                    continue;
                }
                $this->line(($dry ? '[dry-run] ' : '')."{$user->email} → $step ({$site->slug})");
                if (! $dry) {
                    $to = $dest === 'site' ? route('public.site', $site->slug, absolute: false) : route('sites.editor', $site->slug, absolute: false);
                    $accounts->send($user, $subject, [
                        'preheader' => $preheader,
                        'title' => $title,
                        'image' => in_array($step, ['j6', 'j10'], true) ? ($site->site_data['images']['hero'] ?? null) : null,
                        'intro' => 'Bonjour '.\Illuminate\Support\Str::before($user->name, ' ').",\n\n".$intro,
                        'bullets' => $bullets,
                        'rows'  => [['Votre site', 'https://'.$site->slug.'.joow.fr']],
                        'cta'   => ['label' => $button, 'url' => $accounts->magicLink($user, $to, 7)],
                        'note'  => 'Une question ? Répondez simplement à cet email. Pour ne plus recevoir ces conseils : '.$accounts->unsubscribeLink($user),
                    ]);
                    $accounts->mark($site, 'nurture_'.$step, ['last_nurture_day' => $today]);
                    $sent++;
                }
                break; // une seule étape par jour
            }
        }
        $this->info("$sent email(s) envoyé(s).");

        return self::SUCCESS;
    }
}
