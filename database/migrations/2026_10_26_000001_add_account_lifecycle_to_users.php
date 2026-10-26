<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');   // null = compte créé sans mot de passe choisi
            $table->timestamp('marketing_optout_at')->nullable()->after('remember_token');
            $table->string('source', 40)->nullable()->after('marketing_optout_at'); // site | intake | refonte | studio | google | login
            $table->json('nurture')->nullable()->after('source');                  // étapes de relance envoyées {step: date}
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_set_at', 'marketing_optout_at', 'source', 'nurture']);
        });
    }
};
