<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('sku', 60);
            $t->unsignedBigInteger('count')->default(0);
            $t->unsignedBigInteger('units')->default(0); // jetons (IA texte)
            $t->unique(['day', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage');
    }
};
