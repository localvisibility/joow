<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Factures internalisées (remplace les fichiers /data/invoices/*.json d'OVH).
// Générées au checkout ET à chaque renouvellement (corrige le bug factures).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();     // FAC-2026-00001
            $table->string('site_slug')->nullable()->index();
            $table->string('client_email')->index();
            $table->string('client_name')->nullable();
            $table->string('module_name');                  // "Abonnement Starter", "Éditeur + Maintenance"…
            $table->decimal('price_ht', 10, 2);
            $table->decimal('tva_rate', 5, 2)->default(20);
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_id')->nullable()->index(); // stripe invoice/pi id — clé d'idempotence
            $table->string('status')->default('paid');
            $table->longText('html')->nullable();           // rendu HTML de la facture
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
