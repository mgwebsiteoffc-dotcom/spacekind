<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id');
            $t->foreignId('worker_id');
            $t->foreignId('project_id')->nullable();
            $t->uuid('entry_key');
            $t->string('kind', 12);
            $t->date('paid_on');
            $t->unsignedInteger('amount_paise');
            $t->string('method', 20);
            $t->string('reference', 100)->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->string('reversal_reason', 500)->nullable();
            $t->timestamps();
            $t->unique(['worker_id', 'entry_key']);
            $t->index(['business_id', 'worker_id', 'paid_on']);
            $t->foreign(['business_id', 'worker_id'])->references(['business_id', 'id'])->on('workers')->restrictOnDelete();
            $t->foreign(['business_id', 'project_id'])->references(['business_id', 'id'])->on('projects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_payments');
    }
};
