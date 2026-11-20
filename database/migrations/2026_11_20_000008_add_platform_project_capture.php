<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', fn (Blueprint $t) => $t->json('capture')->nullable());
        Schema::create('platform_briefs', function (Blueprint $t) {
            $t->id();
            $t->uuid('submission_key')->unique();
            $t->string('name', 100);
            $t->string('phone', 24);
            $t->string('city', 60);
            $t->unsignedInteger('floor_area');
            $t->string('budget', 80);
            $t->string('scope', 80);
            $t->timestamp('consented_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_briefs');
        Schema::table('site_settings', fn (Blueprint $t) => $t->dropColumn('capture'));
    }
};
