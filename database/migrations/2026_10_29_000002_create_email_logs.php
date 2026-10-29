<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Journal des emails envoyés par Joow à ses prospects et clients (envoi + ouverture). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('to')->index();
            $t->string('subject', 255);
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->timestamp('sent_at')->index();
            $t->timestamp('opened_at')->nullable();
            $t->unsignedInteger('opens')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
