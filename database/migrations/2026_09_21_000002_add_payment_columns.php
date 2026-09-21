<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paiement en ligne (Stripe Connect) : compte connecté par site, acomptes sur
// séjours, empreinte bancaire anti no-show sur réservations de table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->string('stripe_account_id')->nullable()->after('stripe_subscription_id');
            $t->boolean('stripe_charges_enabled')->default(false)->after('stripe_account_id');
        });
        Schema::table('room_bookings', function (Blueprint $t) {
            $t->decimal('deposit_amount', 10, 2)->nullable()->after('total');
            $t->string('payment_status')->default('none')->after('status'); // none | pending | paid | failed
            $t->text('payment_url')->nullable();
            $t->string('stripe_session_id')->nullable()->index();
            $t->string('stripe_payment_intent_id')->nullable();
        });
        Schema::table('reservations', function (Blueprint $t) {
            $t->decimal('hold_amount', 10, 2)->nullable()->after('covers');
            $t->string('payment_status')->default('none')->after('status'); // none | pending | authorized | captured | released
            $t->string('stripe_session_id')->nullable()->index();
            $t->string('stripe_payment_intent_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn(['stripe_account_id', 'stripe_charges_enabled']));
        Schema::table('room_bookings', fn (Blueprint $t) => $t->dropColumn(['deposit_amount', 'payment_status', 'payment_url', 'stripe_session_id', 'stripe_payment_intent_id']));
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn(['hold_amount', 'payment_status', 'stripe_session_id', 'stripe_payment_intent_id']));
    }
};
