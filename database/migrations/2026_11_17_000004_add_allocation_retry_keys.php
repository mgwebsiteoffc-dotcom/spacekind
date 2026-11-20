<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $t) {
            $t->uuid('submission_key');
            $t->unique(['business_id', 'submission_key'], 'allocation_retry_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $t) {
            $t->dropUnique('allocation_retry_unique');
            $t->dropColumn('submission_key');
        });
    }
};
