<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->unique(['business_id', 'id'], 'projects_attendance_tenant_id'));
        Schema::create('worker_attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id');
            $t->foreignId('worker_id');
            $t->date('work_date');
            $t->foreignId('first_project_id')->nullable();
            $t->foreignId('second_project_id')->nullable();
            $t->boolean('absent')->default(false);
            $t->string('note', 500)->nullable();
            $t->unsignedInteger('daily_paise')->nullable();
            $t->unsignedInteger('earned_paise')->default(0);
            $t->unsignedInteger('revision');
            $t->timestamps();
            $t->unique(['worker_id', 'work_date']);
            $t->index(['business_id', 'work_date']);
            $t->foreign(['business_id', 'worker_id'])->references(['business_id', 'id'])->on('workers')->restrictOnDelete();
            foreach (['first_project_id', 'second_project_id'] as $site) {
                $t->foreign(['business_id', $site])->references(['business_id', 'id'])->on('projects')->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_attendances');
        Schema::table('projects', fn (Blueprint $t) => $t->dropUnique('projects_attendance_tenant_id'));
    }
};
