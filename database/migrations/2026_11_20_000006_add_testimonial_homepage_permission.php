<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', fn (Blueprint $t) => $t->boolean('show_on_home')->default(false));
    }

    public function down(): void
    {
        Schema::table('testimonials', fn (Blueprint $t) => $t->dropColumn('show_on_home'));
    }
};
