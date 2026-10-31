<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pages d'offre : formulaire à étapes, étapes « comment ça marche », FAQ, garanties (communs aux variantes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', fn (Blueprint $t) => $t->json('content')->nullable()->after('variants'));
    }

    public function down(): void
    {
        Schema::table('landing_pages', fn (Blueprint $t) => $t->dropColumn('content'));
    }
};
