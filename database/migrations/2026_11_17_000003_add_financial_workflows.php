<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->unique(['business_id', 'client_id', 'id'], 'projects_finance_tenant_client_unique'));
        Schema::create('billing_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
            $t->json('data');
            $t->string('logo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('catalogue_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('name', 160);
            $t->string('category', 20);
            $t->string('unit', 20);
            $t->unsignedBigInteger('rate_minor');
            $t->unsignedInteger('tax_bps')->default(0);
            $t->string('hsn_sac', 12)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['business_id', 'is_active']);
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('kind', 16);
            $t->string('financial_year', 7);
            $t->unsignedInteger('last_number')->default(0);
            $t->timestamps();
            $t->unique(['business_id', 'kind', 'financial_year'], 'doc_sequence_unique');
        });
        Schema::create('financial_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('project_id')->nullable();
            $t->string('kind', 16);
            $t->string('status', 16)->default('draft');
            $t->string('number', 40)->nullable();
            $t->string('financial_year', 7)->nullable();
            $t->uuid('draft_key');
            $t->unsignedInteger('lock_version')->default(1);
            $t->unsignedBigInteger('source_quote_id')->nullable();
            $t->string('reference', 120)->nullable();
            $t->date('document_date');
            $t->date('due_date')->nullable();
            $t->json('customer');
            $t->char('place_of_supply', 2)->nullable();
            $t->json('items');
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('subtotal_minor')->default(0);
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('taxable_minor')->default(0);
            $t->unsignedBigInteger('cgst_minor')->default(0);
            $t->unsignedBigInteger('sgst_minor')->default(0);
            $t->unsignedBigInteger('igst_minor')->default(0);
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->json('snapshot')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->string('decision', 16)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 500)->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason', 500)->nullable();
            $t->dateTime('reminder_at')->nullable();
            $t->string('reminder_note', 180)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'id']);
            $t->unique(['business_id', 'client_id', 'id'], 'doc_client_identity');
            $t->unique(['business_id', 'number']);
            $t->unique(['business_id', 'draft_key']);
            $t->unique(['business_id', 'source_quote_id']);
            $t->index(['business_id', 'kind', 'status']);
            $t->foreign(['business_id', 'client_id'])->references(['business_id', 'id'])->on('clients')->restrictOnDelete();
            $t->foreign(['business_id', 'client_id', 'project_id'], 'doc_project_client_fk')->references(['business_id', 'client_id', 'id'])->on('projects')->restrictOnDelete();
            $t->foreign(['business_id', 'source_quote_id'], 'doc_source_quote_fk')->references(['business_id', 'id'])->on('financial_documents')->restrictOnDelete();
        });
        Schema::create('customer_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedBigInteger('receipt_id');
            $t->uuid('submission_key');
            $t->unsignedBigInteger('amount_minor');
            $t->date('paid_on');
            $t->string('method', 20);
            $t->string('reference', 120)->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->string('reversal_reason', 500)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'id']);
            $t->unique(['business_id', 'client_id', 'id'], 'payment_client_identity');
            $t->unique(['business_id', 'submission_key']);
            $t->unique(['business_id', 'receipt_id']);
            $t->foreign(['business_id', 'client_id'])->references(['business_id', 'id'])->on('clients')->restrictOnDelete();
            $t->foreign(['business_id', 'client_id', 'project_id'], 'payment_project_client_fk')->references(['business_id', 'client_id', 'id'])->on('projects')->restrictOnDelete();
            $t->foreign(['business_id', 'client_id', 'receipt_id'], 'payment_receipt_client_fk')->references(['business_id', 'client_id', 'id'])->on('financial_documents')->restrictOnDelete();
        });
        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('payment_id');
            $t->unsignedBigInteger('invoice_id');
            $t->unsignedBigInteger('amount_minor');
            $t->timestamp('reversed_at')->nullable();
            $t->timestamps();
            $t->foreign(['business_id', 'client_id', 'payment_id'], 'allocation_payment_fk')->references(['business_id', 'client_id', 'id'])->on('customer_payments')->restrictOnDelete();
            $t->foreign(['business_id', 'client_id', 'invoice_id'], 'allocation_invoice_fk')->references(['business_id', 'client_id', 'id'])->on('financial_documents')->restrictOnDelete();
            $t->index(['business_id', 'invoice_id', 'reversed_at']);
        });
        Schema::create('document_shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('document_id');
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->foreign(['business_id', 'document_id'])->references(['business_id', 'id'])->on('financial_documents')->restrictOnDelete();
        });
        Schema::create('platform_billing_settings', function (Blueprint $t) {
            $t->id();
            $t->json('data');
            $t->timestamps();
        });
        Schema::create('platform_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('financial_year', 7)->unique();
            $t->unsignedInteger('last_number')->default(0);
            $t->timestamps();
        });
        Schema::create('platform_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->uuid('submission_key')->unique();
            $t->string('number', 40)->nullable()->unique();
            $t->string('status', 16)->default('draft');
            $t->string('service', 30);
            $t->unsignedInteger('quantity');
            $t->date('document_date');
            $t->date('due_date');
            $t->date('period_start');
            $t->date('period_end');
            $t->json('customer');
            $t->char('place_of_supply', 2);
            $t->json('snapshot')->nullable();
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->string('void_reason', 500)->nullable();
            $t->timestamps();
        });
        Schema::create('platform_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_invoice_id')->constrained()->restrictOnDelete();
            $t->uuid('submission_key')->unique();
            $t->unsignedBigInteger('amount_minor');
            $t->date('paid_on');
            $t->string('reference', 120);
            $t->timestamp('reversed_at')->nullable();
            $t->string('reversal_reason', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['financial_documents', 'customer_payments', 'platform_invoices', 'platform_payments'] as $protected) {
            if (Schema::hasTable($protected) && DB::table($protected)->exists()) {
                throw new RuntimeException('Financial history exists. Refusing destructive rollback; use a reviewed forward correction.');
            }
        }
        foreach (['platform_payments', 'platform_invoices', 'platform_sequences', 'platform_billing_settings', 'document_shares', 'payment_allocations', 'customer_payments', 'financial_documents', 'document_sequences', 'catalogue_items', 'billing_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        // InnoDB may retire an implicit FK index when a covering unique is added.
        // Retain/recreate the original client FK support before dropping that unique.
        if (! Schema::hasIndex('projects', 'projects_business_id_client_id_foreign')) {
            Schema::table('projects', fn (Blueprint $t) => $t->index(['business_id', 'client_id'], 'projects_business_id_client_id_foreign'));
        }
        Schema::table('projects', fn (Blueprint $t) => $t->dropUnique('projects_finance_tenant_client_unique'));
    }
};
