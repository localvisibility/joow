<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Domaines personnalisés : suivi DNS / certificat, achat Hostinger. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('domain_status', 30)->nullable();        // pending_dns | dns_ok | active | error | registering
            $table->text('domain_error')->nullable();
            $table->timestamp('domain_checked_at')->nullable();
            $table->boolean('domain_www_ok')->default(false);
            $table->string('domain_source', 20)->nullable();        // own | hostinger
            $table->string('domain_order_id', 60)->nullable();
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('domain_activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn(['domain_status', 'domain_error', 'domain_checked_at', 'domain_www_ok', 'domain_source', 'domain_order_id', 'domain_expires_at', 'domain_activated_at']));
    }
};
