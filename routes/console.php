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

// Rappels de réservation la veille — email brandé + SMS (module Réservation Restaurant)
Artisan::command('modules:reservation-reminders', function (\App\Services\Notifier $notifier) {
    $tomorrow = now()->addDay()->toDateString();
    $rows = Reservation::whereDate('date', $tomorrow)->where('status', 'confirmed')->where('reminder_sent', false)
        ->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone'))->get();
    $sites = \App\Models\Site::whereIn('slug', $rows->pluck('site_slug')->unique())->get()->keyBy('slug');
    foreach ($rows as $r) {
        try {
            if ($site = $sites[$r->site_slug] ?? null) {
                $notifier->reservationReminder($site, $r, \App\Services\Modules\ReservationAvailability::config($site));
            }
            $r->update(['reminder_sent' => true]);
        } catch (\Throwable $e) {
            Log::warning('Rappel réservation : '.$e->getMessage());
        }
    }
    $this->info(count($rows).' rappel(s) envoyé(s).');
})->purpose('Envoie les rappels de réservation de la veille');

Schedule::command('modules:ical-sync')->hourly();
Schedule::command('modules:reservation-reminders')->dailyAt('18:00');
