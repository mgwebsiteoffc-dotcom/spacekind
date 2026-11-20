<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_credentials', function (Blueprint $t) {
            $t->id();
            $t->string('mode', 4)->unique();
            $t->string('key_id', 80);
            $t->text('key_secret');
            $t->timestamps();
        });
        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->string('purpose', 40)->unique();
            $t->string('subject', 160);
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('attribution', 100);
            $t->string('quote', 1000);
            $t->boolean('is_visible')->default(false);
            $t->timestamp('permission_confirmed_at');
            $t->timestamps();
            $t->index(['business_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('razorpay_credentials');
    }
};
