<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Support client : messages à l'équipe et questions posées à l'assistant d'aide. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('site_slug')->nullable();
            $t->string('subject', 160);
            $t->text('message');
            $t->string('status', 12)->default('open'); // open | closed
            $t->timestamps();
        });
        Schema::create('support_questions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->text('question');
            $t->text('answer')->nullable();
            $t->boolean('escalate')->default(false);   // l'assistant conseille de contacter l'équipe
            $t->boolean('helpful')->nullable();        // 👍 / 👎 du client
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_questions');
        Schema::dropIfExists('support_tickets');
    }
};
