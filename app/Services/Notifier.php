<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Reservation;
use App\Models\RoomBooking;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Notifications brandées (email HTML aux couleurs du site + SMS) pour les
 * réservations de table, séjours et demandes.
 */
class Notifier
{
    public function __construct(private SmsSender $sms) {}

    // ───────────────────────── Réservations de table ─────────────────────────

    public function reservationCreated(Site $site, Reservation $r, array $cfg): void
    {
        $b = $this->brand($site);
        $when = $this->when($r->date, (string) $r->time);
        $confirmed = $r->status === 'confirmed';
        $cancel = URL::signedRoute('public.reservation.cancel', ['reservation' => $r->id]);

        if ($r->email) {
            $this->mail($site, $r->email, ($confirmed ? 'Réservation confirmée — ' : 'Demande de réservation — ').$b['name'], [
                'title' => $confirmed ? 'Votre table est réservée' : 'Demande bien reçue',
                'intro' => ($cfg['confirmation_message'] ?? '') ?: ($confirmed ? 'Nous avons le plaisir de confirmer votre réservation.' : 'Nous revenons vers vous très vite pour confirmer.'),
                'rows'  => array_filter([['Date', $when['date']], ['Heure', $when['time']], ['Couverts', $r->covers], $r->hold_amount ? ['Empreinte bancaire', number_format((float) $r->hold_amount, 2, ',', ' ').' € (débitée uniquement en cas de no-show)'] : null]),
                'cta'   => ['label' => 'Ajouter à mon agenda', 'url' => $this->gcal($b['name'].' — réservation', $when['start'], $when['end'], $b['address'])],
                'cta2'  => ['label' => 'Annuler ma réservation', 'url' => $cancel],
                'note'  => 'Un empêchement ? Merci de nous prévenir en annulant via le lien ci-dessus'.($b['phone'] ? ' ou au '.$b['phone'] : '').'.',
            ], $this->ics($b['name'].' — réservation', $when['start'], $when['end'], $b['address']));
        }
        if (! empty($cfg['sms_confirm']) && $r->phone) {
            $this->sms->send($r->phone, ($confirmed ? 'Réservation confirmée' : 'Demande reçue')." chez {$b['name']} : {$when['date']} à {$when['time']}, {$r->covers} pers. Annulation : $cancel", $this->smsSender($b['name']));
        }

        $this->owner($site, $cfg['notify_email'] ?? null, 'Nouvelle réservation — '.$b['name'], [
            'title' => $confirmed ? 'Nouvelle réservation confirmée' : 'Nouvelle demande de réservation',
            'rows'  => array_filter([['Date', $when['date']], ['Heure', $when['time']], ['Couverts', $r->covers], ['Nom', $r->name], ['Téléphone', $r->phone], $r->email ? ['Email', $r->email] : null, $r->notes ? ['Demande', $r->notes] : null, $r->hold_amount ? ['Empreinte', number_format((float) $r->hold_amount, 2, ',', ' ').' €'] : null]),
            'cta'   => ['label' => 'Gérer mes réservations', 'url' => route('reservations.index')],
        ]);
    }

    public function reservationStatus(Site $site, Reservation $r, array $cfg): void
    {
        $b = $this->brand($site);
        $when = $this->when($r->date, (string) $r->time);
        $map = ['confirmed' => ['Votre table est confirmée', 'Nous avons le plaisir de confirmer votre réservation.'], 'cancelled' => ['Réservation annulée', 'Votre réservation a été annulée. Au plaisir de vous accueillir une prochaine fois.']];
        if (! isset($map[$r->status])) {
            return;
        }
        [$title, $intro] = $map[$r->status];
        if ($r->email) {
            $this->mail($site, $r->email, $title.' — '.$b['name'], ['title' => $title, 'intro' => $intro, 'rows' => [['Date', $when['date']], ['Heure', $when['time']], ['Couverts', $r->covers]]]);
        }
        if (! empty($cfg['sms_confirm']) && $r->phone) {
            $this->sms->send($r->phone, "{$b['name']} : {$title}. {$when['date']} à {$when['time']}, {$r->covers} pers.", $this->smsSender($b['name']));
        }
    }

    public function reservationReminder(Site $site, Reservation $r, array $cfg): void
    {
        $b = $this->brand($site);
        $when = $this->when($r->date, (string) $r->time);
        if ($r->email) {
            $this->mail($site, $r->email, 'Rappel : votre réservation demain — '.$b['name'], [
                'title' => 'À demain !',
                'intro' => "Petit rappel de votre réservation chez {$b['name']}.",
                'rows'  => [['Date', $when['date']], ['Heure', $when['time']], ['Couverts', $r->covers]],
                'cta2'  => ['label' => 'Je ne peux pas venir — annuler', 'url' => URL::signedRoute('public.reservation.cancel', ['reservation' => $r->id])],
            ]);
        }
        if (! empty($cfg['sms_reminder']) && $r->phone) {
            $this->sms->send($r->phone, "Rappel {$b['name']} : votre table demain {$when['date']} à {$when['time']} ({$r->covers} pers.). À demain !", $this->smsSender($b['name']));
        }
    }

    // ───────────────────────────────── Séjours ─────────────────────────────────

    public function stayRequested(Site $site, RoomBooking $bk, array $cfg): void
    {
        $b = $this->brand($site);
        $rows = $this->stayRows($bk);
        if ($bk->email) {
            $this->mail($site, $bk->email, 'Demande de séjour reçue — '.$b['name'], [
                'title' => 'Demande bien reçue',
                'intro' => 'Merci ! Nous vérifions les disponibilités et revenons vers vous très rapidement.',
                'rows'  => $rows,
                'cta2'  => ['label' => 'Annuler ma demande', 'url' => URL::signedRoute('public.stay.cancel', ['booking' => $bk->id])],
            ]);
        }
        $this->owner($site, $cfg['notify_email'] ?? null, 'Nouvelle demande de séjour — '.$b['name'], [
            'title' => 'Nouvelle demande de séjour',
            'rows'  => array_merge($rows, array_filter([['Nom', $bk->name], ['Téléphone', $bk->phone], $bk->email ? ['Email', $bk->email] : null, $bk->notes ? ['Message', $bk->notes] : null])),
            'cta'   => ['label' => 'Confirmer ou refuser', 'url' => route('reservations.index')],
        ]);
    }

    public function stayConfirmed(Site $site, RoomBooking $bk, ?string $payUrl = null): void
    {
        $b = $this->brand($site);
        $ci = Carbon::parse($bk->check_in); $co = Carbon::parse($bk->check_out);
        $data = [
            'title' => 'Votre séjour est confirmé',
            'intro' => $payUrl ? "Pour garantir votre réservation, merci de régler l'acompte de ".number_format((float) $bk->deposit_amount, 2, ',', ' ').' €.' : 'Nous avons le plaisir de confirmer votre séjour. À très bientôt !',
            'rows'  => $this->stayRows($bk),
            'cta'   => $payUrl ? ['label' => 'Régler l\'acompte', 'url' => $payUrl] : ['label' => 'Ajouter à mon agenda', 'url' => $this->gcal($b['name'].' — séjour', $ci->setTime(15, 0), $co->setTime(11, 0), $b['address'])],
            'cta2'  => ['label' => 'Annuler', 'url' => URL::signedRoute('public.stay.cancel', ['booking' => $bk->id])],
        ];
        if ($bk->email) {
            $this->mail($site, $bk->email, 'Séjour confirmé — '.$b['name'], $data, $this->ics($b['name'].' — séjour', $ci->copy()->setTime(15, 0), $co->copy()->setTime(11, 0), $b['address']));
        }
        if ($bk->phone) {
            $this->sms->send($bk->phone, "{$b['name']} : séjour confirmé du {$ci->format('d/m')} au {$co->format('d/m')}.".($payUrl ? " Acompte à régler : $payUrl" : ''), $this->smsSender($b['name']));
        }
    }

    public function stayPaid(Site $site, RoomBooking $bk): void
    {
        $b = $this->brand($site);
        if ($bk->email) {
            $this->mail($site, $bk->email, 'Acompte reçu — '.$b['name'], ['title' => 'Acompte bien reçu', 'intro' => 'Merci ! Votre réservation est garantie. Nous avons hâte de vous accueillir.', 'rows' => $this->stayRows($bk)]);
        }
        $this->owner($site, $site->module('payment')['notify_email'] ?? null, 'Acompte reçu — '.$b['name'], ['title' => 'Acompte encaissé', 'rows' => array_merge($this->stayRows($bk), [['Client', $bk->name]]), 'cta' => ['label' => 'Voir', 'url' => route('reservations.index')]]);
    }

    public function stayCancelled(Site $site, RoomBooking $bk): void
    {
        $b = $this->brand($site);
        if ($bk->email) {
            $this->mail($site, $bk->email, 'Séjour annulé — '.$b['name'], ['title' => 'Séjour annulé', 'intro' => 'Votre demande de séjour a été annulée. Au plaisir de vous accueillir une prochaine fois.', 'rows' => $this->stayRows($bk)]);
        }
    }

    // ───────────────────────────────── Studio ─────────────────────────────────

    /** Lien d'édition privé du Studio (site créé sans compte). */
    public function editLink(Site $site, string $to): void
    {
        $b = $this->brand($site);
        $link = route('sites.editor', $site->slug).'?t='.$site->editToken();
        $this->mail($site, $to, 'Votre site '.$b['name'].' vous attend dans le Studio', [
            'title' => 'Votre site est prêt à personnaliser',
            'intro' => "Voici votre lien privé pour retrouver et modifier votre site quand vous voulez : textes, photos, pages, couleurs, formulaire… Gratuit, sans engagement. Mettez-le en ligne quand il est parfait.",
            'rows'  => [['Aperçu', $b['url']], ['Studio', $link]],
            'cta'   => ['label' => 'Ouvrir le Studio', 'url' => $link],
            'note'  => 'Gardez cet email : ce lien est personnel. Vous pouvez aussi créer un compte gratuit avec cette adresse pour tout retrouver dans votre espace.',
        ]);
    }

    // ───────────────────────────────── Demandes ─────────────────────────────────

    public function leadReceived(Site $site, Lead $lead): void
    {
        $b = $this->brand($site);
        $type = ['reservation' => 'réservation', 'rdv' => 'rendez-vous', 'devis' => 'devis'][$lead->type] ?? 'contact';
        $rows = array_filter([['Type', ucfirst($type)], ['Nom', $lead->name], ['Téléphone', $lead->phone], $lead->email ? ['Email', $lead->email] : null, $lead->message ? ['Message', $lead->message] : null]);
        foreach ((array) ($lead->payload ?? []) as $k => $v) {
            if (is_scalar($v) && $v !== '') {
                $rows[] = [ucfirst((string) $k), (string) $v];
            }
        }
        $this->owner($site, null, "Nouvelle demande de {$type} — {$b['name']}", ['title' => "Nouvelle demande de {$type}", 'rows' => array_values($rows), 'cta' => ['label' => 'Voir mes demandes', 'url' => route('leads.index')]]);
        if ($lead->email) {
            $this->mail($site, $lead->email, 'Demande bien reçue — '.$b['name'], ['title' => 'Merci, c\'est bien reçu', 'intro' => "Nous avons bien reçu votre demande de {$type} et revenons vers vous rapidement.", 'rows' => array_values(array_filter($rows, fn ($r) => ! in_array($r[0], ['Nom', 'Téléphone', 'Email'], true)))]);
        }
    }

    // ───────────────────────────────── helpers ─────────────────────────────────

    private function brand(Site $site): array
    {
        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $cfg = config('sectors.'.($site->sector ?: 'service')) ?? config('sectors.service');

        return [
            'name'    => $b['name'] ?? $site->name,
            'accent'  => $d['accent'] ?? $cfg['color'] ?? '#4f46e5',
            'phone'   => $b['phone'] ?? null,
            'address' => $b['address'] ?? null,
            'email'   => $b['email'] ?? $site->owner_email,
            'url'     => 'https://'.$site->slug.'.joow.fr',
        ];
    }

    private function mail(Site $site, string $to, string $subject, array $data, ?string $ics = null): void
    {
        $b = $this->brand($site);
        try {
            Mail::send('emails.notification', $data + ['brand' => $b], function ($m) use ($to, $subject, $b, $ics) {
                $m->to($to)->subject($subject)->from(config('mail.from.address'), $b['name'].' via Joow');
                if ($b['email']) {
                    $m->replyTo($b['email'], $b['name']);
                }
                if ($ics) {
                    $m->attachData($ics, 'reservation.ics', ['mime' => 'text/calendar']);
                }
            });
        } catch (\Throwable $e) {
            Log::warning("Mail ($subject) : ".$e->getMessage());
        }
    }

    private function owner(Site $site, ?string $to, string $subject, array $data): void
    {
        $to = $to ?: ($site->owner_email ?: ($site->site_data['business']['email'] ?? null));
        if ($to) {
            $this->mail($site, $to, $subject, $data);
        }
    }

    private function when($date, string $time): array
    {
        $start = Carbon::parse(Carbon::parse($date)->toDateString().' '.substr($time, 0, 5));

        return ['date' => $start->locale('fr')->isoFormat('dddd D MMMM YYYY'), 'time' => $start->format('H\hi'), 'start' => $start, 'end' => $start->copy()->addHours(2)];
    }

    private function stayRows(RoomBooking $bk): array
    {
        $ci = Carbon::parse($bk->check_in); $co = Carbon::parse($bk->check_out);

        return array_values(array_filter([
            ['Arrivée', $ci->locale('fr')->isoFormat('dddd D MMMM YYYY')], ['Départ', $co->locale('fr')->isoFormat('dddd D MMMM YYYY')],
            ['Nuits', $bk->nights], ['Personnes', $bk->guests],
            $bk->room ? ['Hébergement', $bk->room->name] : null,
            $bk->total ? ['Total', number_format((float) $bk->total, 2, ',', ' ').' €'] : null,
            $bk->deposit_amount ? ['Acompte', number_format((float) $bk->deposit_amount, 2, ',', ' ').' €'.($bk->payment_status === 'paid' ? ' (réglé)' : '')] : null,
        ]));
    }

    private function gcal(string $title, Carbon $start, Carbon $end, ?string $location): string
    {
        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode($title)
            .'&dates='.$start->copy()->utc()->format('Ymd\THis\Z').'/'.$end->copy()->utc()->format('Ymd\THis\Z')
            .'&location='.rawurlencode((string) $location);
    }

    private function ics(string $title, Carbon $start, Carbon $end, ?string $location): string
    {
        $esc = fn ($s) => str_replace([',', ';', "\n"], ['\,', '\;', '\n'], (string) $s);

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Joow//FR\r\nBEGIN:VEVENT\r\nUID:".uniqid('', true)."@joow.fr\r\nDTSTAMP:".now()->utc()->format('Ymd\THis\Z')
            ."\r\nDTSTART:".$start->copy()->utc()->format('Ymd\THis\Z')."\r\nDTEND:".$end->copy()->utc()->format('Ymd\THis\Z')
            ."\r\nSUMMARY:".$esc($title)."\r\nLOCATION:".$esc($location)."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }

    private function smsSender(string $name): string
    {
        $s = preg_replace('/[^A-Za-z0-9]/', '', \Illuminate\Support\Str::ascii($name));

        return substr($s ?: 'Joow', 0, 11);
    }
}
