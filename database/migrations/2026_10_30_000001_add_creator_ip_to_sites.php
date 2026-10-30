<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adresse IP de création d'un site : limite des créations gratuites et repérage des multi-sites. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->string('creator_ip', 45)->nullable()->index();
            $t->string('creator_device', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn(['creator_ip', 'creator_device']));
    }
};
