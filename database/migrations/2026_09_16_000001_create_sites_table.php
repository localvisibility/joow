<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Table centrale : un site généré/hébergé pour un client.
// Reprend le schéma Supabase `sites` en le nettoyant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('sector')->nullable();          // avocat, restaurant, gite…
            $table->string('status')->default('preview');  // preview | paid | published
            $table->string('place_id')->nullable();

            // Coordonnées / contact
            $table->string('city')->nullable();
            $table->string('department')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('maps_url')->nullable();

            // Hébergement / domaine
            $table->string('preview_url')->nullable();
            $table->string('live_url')->nullable();
            $table->string('subdomain')->nullable();
            $table->string('custom_domain')->nullable();
            $table->boolean('domain_verified')->default(false);
            $table->boolean('domain_ssl_active')->default(false);

            // Propriétaire (relié aux users applicatifs)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_email')->nullable()->index();
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();

            // Abonnement / paiement (Cashier gère le détail Stripe)
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('subscription_status')->nullable();
            $table->string('hosting_plan')->nullable();
            $table->timestamp('subscription_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('payment_failed_at')->nullable();
            $table->string('purchase_type')->default('subscription');

            // Contenu généré + modules
            $table->longText('html_content')->nullable();
            $table->json('pages')->nullable();
            $table->json('site_data')->nullable();
            $table->json('modules')->nullable();
            $table->json('legal_content')->nullable();
            $table->json('contact_form_config')->nullable();

            $table->string('source')->default('app');
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sector']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
