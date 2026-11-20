<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_invoice_id')->constrained();
            $table->uuid('reference')->unique();
            $table->string('mode', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->string('provider_id')->nullable();
            $table->string('state', 20)->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('platform_checkouts');
    }
};
