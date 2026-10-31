<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fil de discussion d'une demande de support (réponses de l'équipe et du client). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_replies', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ticket_id')->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('author', 10);              // team | client
            $t->text('message');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_replies');
    }
};
