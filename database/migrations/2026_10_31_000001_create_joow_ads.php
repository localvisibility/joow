<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module Joow Ads : packs publicitaires générés, landing pages d'offre et leurs tests A/B. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_packs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->index();
            $t->string('goal', 20);                 // calls | leads | bookings | visits
            $t->string('offer')->nullable();
            $t->json('pack');                       // stratégie, accroches, textes, titres, visuels
            $t->timestamps();
        });

        Schema::create('landing_pages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('site_id')->index();
            $t->string('site_slug')->index();
            $t->string('path', 60);                 // URL : <site>/o/<path>/
            $t->string('title');
            $t->string('goal', 20)->default('leads');
            $t->json('variants');                   // [{key, headline, subheadline, offer, bullets[], cta, image}]
            $t->string('status', 12)->default('draft'); // draft | live | paused
            $t->string('winner', 2)->nullable();    // variante gagnante retenue : tout le trafic y va
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'path']);
        });

        Schema::create('landing_stats', function (Blueprint $t) {
            $t->id();
            $t->uuid('landing_id')->index();
            $t->string('variant', 2);
            $t->date('day');
            $t->unsignedInteger('views')->default(0);
            $t->unsignedInteger('conversions')->default(0);
            $t->unsignedInteger('calls')->default(0);
            $t->unique(['landing_id', 'variant', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_stats');
        Schema::dropIfExists('landing_pages');
        Schema::dropIfExists('ads_packs');
    }
};
