<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Créations d'aperçu refusées par le garde-fou (pour vérifier qu'il n'est pas trop strict). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_blocks', function (Blueprint $t) {
            $t->id();
            $t->string('ip', 45)->nullable()->index();
            $t->string('device', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('reason', 20);          // device_email | ip
            $t->unsignedInteger('count')->default(0);
            $t->string('flow', 20)->nullable(); // google | questionnaire | refonte
            $t->string('query')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_blocks');
    }
};
