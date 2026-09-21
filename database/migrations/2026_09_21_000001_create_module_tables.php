<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tables des modules métier (héritées de Local Visibility, en mieux) :
// réservation restaurant, chambres/séjours (+ blocages iCal), menu/carte,
// statistiques journalières, demandes de domaine.
return new class extends Migration
{
    public function up(): void
    {
        // Réservations de table (module Réservation Restaurant)
        Schema::create('reservations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->nullable()->index();
            $t->string('site_slug')->index();
            $t->date('date');
            $t->time('time');
            $t->unsignedSmallInteger('covers')->default(2);
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('pending');   // pending | confirmed | cancelled | noshow | seated
            $t->string('source')->default('website');   // website | manual
            $t->boolean('reminder_sent')->default(false);
            $t->timestamps();
            $t->index(['site_slug', 'date']);
        });

        // Unités louables (chambres, gîtes, emplacements…) — module Calendrier / Chambres
        Schema::create('rooms', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->nullable()->index();
            $t->string('site_slug')->index();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('capacity')->default(2);
            $t->decimal('price_night', 10, 2)->nullable();
            $t->json('photos')->nullable();
            $t->json('amenities')->nullable();
            $t->string('ical_url')->nullable();          // flux Airbnb / Booking / Abritel
            $t->timestamp('ical_synced_at')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Séjours (demandes / réservations de chambres)
        Schema::create('room_bookings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->nullable()->index();
            $t->string('site_slug')->index();
            $t->uuid('room_id')->nullable()->index();
            $t->date('check_in');
            $t->date('check_out');
            $t->unsignedSmallInteger('guests')->default(2);
            $t->unsignedSmallInteger('nights')->default(1);
            $t->decimal('total', 10, 2)->nullable();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('pending');   // pending | confirmed | cancelled
            $t->string('source')->default('website');   // website | manual | ical
            $t->string('external_uid')->nullable()->index();
            $t->timestamps();
            $t->index(['site_slug', 'check_in']);
        });

        // Périodes bloquées (manuelles ou importées iCal) — évite les doubles réservations
        Schema::create('room_blocks', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('site_slug')->index();
            $t->uuid('room_id')->nullable()->index();
            $t->date('start');
            $t->date('end');                            // exclusif (départ)
            $t->string('source')->default('manual');    // manual | ical
            $t->string('external_uid')->nullable()->index();
            $t->string('summary')->nullable();
            $t->timestamps();
        });

        // Menu / Carte
        Schema::create('menu_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->nullable()->index();
            $t->string('site_slug')->index();
            $t->string('category')->default('Plats');
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->boolean('available')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // Statistiques journalières (vues, demandes, réservations)
        Schema::create('site_stats', function (Blueprint $t) {
            $t->id();
            $t->string('site_slug');
            $t->date('day');
            $t->unsignedInteger('views')->default(0);
            $t->unsignedInteger('leads')->default(0);
            $t->unsignedInteger('reservations')->default(0);
            $t->unsignedInteger('bookings')->default(0);
            $t->unsignedInteger('bot_chats')->default(0);
            $t->timestamps();
            $t->unique(['site_slug', 'day']);
        });

        // Demandes de nom de domaine (achat ou connexion d'un domaine existant)
        Schema::create('domain_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->nullable()->index();
            $t->string('site_slug')->index();
            $t->string('domain');
            $t->string('type')->default('connect');     // connect | order
            $t->string('status')->default('pending');   // pending | in_progress | active | rejected
            $t->string('contact_email')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['domain_requests', 'site_stats', 'menu_items', 'room_blocks', 'room_bookings', 'rooms', 'reservations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
