<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->unsignedInteger('completed_project_count')->nullable();
            $table->json('project_highlights')->nullable();
            $table->json('awards')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn(['established_year', 'completed_project_count', 'project_highlights', 'awards']));
    }
};
