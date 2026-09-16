<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Suivi des générations de sites (remplace Supabase `generation_queue`).
// Le travail réel tourne via la file Laravel/Horizon ; cette table sert au
// polling côté client (statut d'avancement) et à l'historique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->string('sector')->nullable();
            $table->string('site_slug')->nullable()->index();
            $table->json('input');                        // place_id, email, options…
            $table->json('result')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_jobs');
    }
};
