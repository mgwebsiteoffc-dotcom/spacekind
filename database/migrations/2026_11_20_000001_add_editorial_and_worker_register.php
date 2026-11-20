<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspiration_edits', function (Blueprint $t) {
            $t->id();
            $t->string('image_key', 40)->unique();
            $t->string('category');
            $t->string('headline');
            $t->string('alt');
            $t->unsignedSmallInteger('position');
        });
        Schema::create('workers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->string('trade', 80);
            $t->timestamps();
            $t->unique(['business_id', 'id']);
        });
        Schema::create('worker_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id');
            $t->foreignId('worker_id');
            $t->date('effective_from');
            $t->unsignedInteger('daily_paise');
            $t->timestamps();
            $t->foreign(['business_id', 'worker_id'])->references(['business_id', 'id'])->on('workers')->restrictOnDelete();
            $t->unique(['worker_id', 'effective_from']);
        });
        // Only the two shipped reference films changed size; never touch uploaded media.
        foreach (['warm-reference.mp4' => 144337, 'kitchen-reference.mp4' => 189010] as $file => $bytes) {
            DB::table('reels')->where('disk', 'public-assets')->where('video_path', 'assets/reels/'.$file)->update(['bytes' => $bytes]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_rates');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('inspiration_edits');
    }
};
