<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Services\Modules\IcalSync;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Synchronisation des calendriers iCal (Airbnb / Booking / Abritel) — module Chambres
Artisan::command('modules:ical-sync', function (IcalSync $ical) {
    $n = 0;
    foreach (Room::whereNotNull('ical_url')->where('active', true)->get() as $room) {
        $n += $ical->syncRoom($room);
    }
    $this->info("iCal : $n événement(s) synchronisé(s).");
})->purpose('Importe les indisponibilités iCal des chambres');

// Rappels de réservation la veille (module Réservation Restaurant)
Artisan::command('modules:reservation-reminders', function () {
    $tomorrow = now()->addDay()->toDateString();
    $rows = Reservation::whereDate('date', $tomorrow)->where('status', 'confirmed')->where('reminder_sent', false)->whereNotNull('email')->get();
    foreach ($rows as $r) {
        try {
            Mail::raw("Rappel : votre réservation demain {$tomorrow} à ".substr((string) $r->time, 0, 5)." pour {$r->covers} personne(s). À très vite !", fn ($m) => $m->to($r->email)->subject('Rappel de votre réservation'));
            $r->update(['reminder_sent' => true]);
        } catch (\Throwable $e) {
            Log::warning('Rappel réservation : '.$e->getMessage());
        }
    }
    $this->info(count($rows).' rappel(s) envoyé(s).');
})->purpose('Envoie les rappels de réservation de la veille');

Schedule::command('modules:ical-sync')->hourly();
Schedule::command('modules:reservation-reminders')->dailyAt('18:00');
