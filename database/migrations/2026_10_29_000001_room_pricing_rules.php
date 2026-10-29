<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chambres : tarifs saisonniers et week-end, durée de séjour, frais (ménage, personne
 * supplémentaire, taxe de séjour), plusieurs flux iCal importés et un flux iCal exporté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $t) {
            $t->decimal('weekend_price', 10, 2)->nullable()->after('price_night');
            $t->json('seasons')->nullable()->after('weekend_price');          // [{name, from:"MM-DD", to:"MM-DD", price, weekend_price, min_nights}]
            $t->unsignedSmallInteger('min_nights')->default(1)->after('seasons');
            $t->unsignedSmallInteger('max_nights')->nullable()->after('min_nights');
            $t->decimal('cleaning_fee', 10, 2)->nullable()->after('max_nights');
            $t->unsignedSmallInteger('included_guests')->nullable()->after('cleaning_fee');
            $t->decimal('extra_guest_fee', 10, 2)->nullable()->after('included_guests'); // par personne et par nuit au-delà de included_guests
            $t->decimal('tourist_tax', 10, 2)->nullable()->after('extra_guest_fee');     // par adulte et par nuit
            $t->json('ical_urls')->nullable()->after('ical_url');             // [{label, url}]
            $t->string('ical_token', 64)->nullable()->after('ical_urls');     // flux exporté
        });
        Schema::table('room_bookings', function (Blueprint $t) {
            $t->json('breakdown')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $t) => $t->dropColumn(['weekend_price', 'seasons', 'min_nights', 'max_nights', 'cleaning_fee', 'included_guests', 'extra_guest_fee', 'tourist_tax', 'ical_urls', 'ical_token']));
        Schema::table('room_bookings', fn (Blueprint $t) => $t->dropColumn('breakdown'));
    }
};
