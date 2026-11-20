<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_documents', function (Blueprint $t) {
            $t->timestamp('sent_at')->nullable();
            $t->string('sent_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('financial_documents')->whereNotNull('sent_at')->exists()) {
            throw new RuntimeException('Quotation delivery history exists; refusing destructive rollback.');
        }Schema::table('financial_documents', fn (Blueprint $t) => $t->dropColumn(['sent_at', 'sent_note']));
    }
};
