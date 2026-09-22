<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crédits IA du Studio : quota mensuel selon la formule + portefeuille rechargeable,
 * journal des mouvements. Connexion Google (identifiant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->integer('ai_credits_monthly')->default(10);      // quota de la période (10 offerts en aperçu)
            $table->integer('ai_credits_wallet')->default(0);        // crédits achetés (persistants)
            $table->integer('ai_credits_used')->default(0);          // total consommé (statistique)
            $table->timestamp('ai_credits_reset_at')->nullable();    // prochain renouvellement du quota (Pro)
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id')->index();
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('reason', 40);            // free_grant, plan_grant, monthly_reset, purchase, ai_action, revert
            $table->json('meta')->nullable();
            $table->string('external_id', 120)->nullable()->unique(); // id de session Stripe (idempotence)
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('google_id'));
        Schema::dropIfExists('credit_transactions');
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn(['ai_credits_monthly', 'ai_credits_wallet', 'ai_credits_used', 'ai_credits_reset_at']));
    }
};
