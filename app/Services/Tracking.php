<?php

namespace App\Services;

/**
 * Événements à déclencher côté navigateur (pixel Meta) : empilés en session, remis une fois
 * à la page suivante via la prop Inertia partagée « track », ou renvoyés dans une réponse JSON.
 */
class Tracking
{
    public static function push(string $name, string $eventId, array $params = []): void
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return; // commandes et jobs : pas de navigateur à informer
        }
        $events = session()->get('track_events', []);
        $events[] = ['name' => $name, 'id' => $eventId, 'params' => $params];
        session()->put('track_events', array_slice($events, -10));
    }

    /** Vide la file (prop partagée ou réponse JSON). */
    public static function pull(): array
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return [];
        }

        return (array) session()->pull('track_events', []);
    }
}
