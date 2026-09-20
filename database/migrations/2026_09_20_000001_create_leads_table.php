<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Demandes reçues depuis un site généré (réservation, RDV, devis, contact).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id')->nullable()->index();
            $table->string('site_slug')->index();
            $table->string('type')->default('contact'); // reservation | rdv | devis | contact
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();         // champs additionnels (date, couverts, motif…)
            $table->string('status')->default('new');     // new | read | archived
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['site_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
