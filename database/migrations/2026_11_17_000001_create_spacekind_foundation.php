<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('slug', 140)->unique();
            $t->string('city', 60);
            $t->string('locality', 120)->nullable();
            $t->string('service', 30)->default('home');
            $t->json('services')->nullable();
            $t->json('tags')->nullable();
            $t->unsignedInteger('budget_min')->default(5);
            $t->unsignedInteger('budget_max')->default(25);
            $t->text('bio')->nullable();
            $t->string('initials', 4)->default('S');
            $t->string('cover_key', 30)->default('hero');
            $t->string('approval_status', 20)->default('pending')->index();
            $t->string('account_status', 20)->default('pilot');
            $t->boolean('is_demo')->default(false);
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role', 20)->default('owner');
            $t->boolean('is_active')->default(true);
            $t->unique(['business_id', 'id']);
        });
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
            $t->string('plan')->default('standard-yearly');
            $t->string('status', 20)->default('pilot');
            $t->unsignedInteger('amount_paise')->default(499900);
            $t->char('currency', 3)->default('INR');
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->timestamps();
        });
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->string('phone', 20);
            $t->string('email')->nullable();
            $t->string('city', 60);
            $t->string('locality', 120);
            $t->unsignedInteger('floor_area')->nullable();
            $t->string('property_type', 40);
            $t->string('configuration', 40)->nullable();
            $t->string('scope', 60);
            $t->string('budget', 40);
            $t->string('readiness', 40);
            $t->text('brief')->nullable();
            $t->string('stage', 24)->default('New');
            $t->foreignId('assigned_to')->nullable();
            $t->string('source', 40)->default('Unknown');
            $t->json('utm')->nullable();
            $t->timestamp('contact_consent_at')->nullable();
            $t->timestamp('attribution_consent_at')->nullable();
            $t->string('consent_version', 30)->nullable();
            $t->boolean('marketing_consent')->default(false);
            $t->dateTime('next_follow_up_at')->nullable();
            $t->string('follow_up_task', 180)->nullable();
            $t->string('lost_reason', 240)->nullable();
            $t->string('plan_path')->nullable();
            $t->uuid('submission_key')->nullable();
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
            $t->unique(['business_id', 'id']);
            $t->unique(['business_id', 'submission_key']);
            $t->index(['business_id', 'stage']);
            $t->index(['business_id', 'next_follow_up_at']);
            $t->foreign(['business_id', 'assigned_to'])->references(['business_id', 'id'])->on('users')->restrictOnDelete();
        });
        Schema::create('lead_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('lead_id');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 30);
            $t->text('body');
            $t->timestamps();
            $t->foreign(['business_id', 'lead_id'])->references(['business_id', 'id'])->on('leads')->cascadeOnDelete();
            $t->index(['business_id', 'lead_id', 'created_at']);
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->string('name', 100);
            $t->string('phone', 20);
            $t->string('email')->nullable();
            $t->string('locality', 120);
            $t->string('source', 40);
            $t->json('utm')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'id']);
            $t->unique(['business_id', 'lead_id']);
            $t->foreign(['business_id', 'lead_id'])->references(['business_id', 'id'])->on('leads')->restrictOnDelete();
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->string('name', 160);
            $t->string('stage', 30)->default('Planned');
            $t->date('target_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('handover_at')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'lead_id']);
            $t->index(['business_id', 'stage']);
            $t->foreign(['business_id', 'client_id'])->references(['business_id', 'id'])->on('clients')->restrictOnDelete();
            $t->foreign(['business_id', 'lead_id'])->references(['business_id', 'id'])->on('leads')->restrictOnDelete();
        });
        Schema::create('gallery_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('disk', 20)->default('local');
            $t->string('caption', 180);
            $t->string('kind', 30)->default('completed');
            $t->boolean('is_visible')->default(true);
            $t->unsignedInteger('position')->default(0);
            $t->timestamp('rights_confirmed_at')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'is_visible', 'position']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80);
            $t->string('subject', 80)->nullable();
            $t->json('changes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'gallery_images', 'projects', 'clients', 'lead_activities', 'leads', 'subscriptions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['business_id']);
            $t->dropUnique(['business_id', 'id']);
            $t->dropColumn(['business_id', 'role', 'is_active']);
        });
        Schema::dropIfExists('businesses');
    }
};
