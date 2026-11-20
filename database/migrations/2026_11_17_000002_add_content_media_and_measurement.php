<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $t) {
            $t->string('seo_title', 70)->nullable();
            $t->string('seo_description', 170)->nullable();
        });
        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('slug', 160)->unique();
            $t->boolean('is_visible')->default(false);
            $t->timestamp('visible_from')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'is_visible']);
        });
        Schema::create('blog_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 140);
            $t->string('excerpt', 400);
            $t->text('body');
            $t->string('category', 40)->default('Planning & budgets');
            $t->json('tags')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('cover_alt', 180)->nullable();
            $t->string('byline_mode', 20)->default('business');
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_name', 100);
            $t->string('seo_title', 70)->nullable();
            $t->string('seo_description', 170)->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('publish_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 500)->nullable();
            $t->timestamps();
            $t->index(['blog_post_id', 'status', 'publish_at']);
        });
        Schema::create('reels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('title', 120);
            $t->text('caption');
            $t->string('kind', 20)->default('completed');
            $t->string('category', 30)->default('Living');
            $t->string('associated_url', 1000);
            $t->string('video_path');
            $t->string('cover_path');
            $t->string('disk', 20)->default('local');
            $t->string('mime', 30);
            $t->unsignedBigInteger('bytes');
            $t->decimal('duration', 6, 2);
            $t->unsignedInteger('position')->default(0);
            $t->string('status', 20)->default('draft');
            $t->timestamp('rights_confirmed_at');
            $t->timestamp('blocked_at')->nullable();
            $t->string('moderation_note', 500)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'status', 'position']);
        });
        Schema::create('campaign_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->string('destination_type', 20);
            $t->unsignedBigInteger('destination_id')->nullable();
            $t->string('destination_path', 300);
            $t->json('utm');
            $t->char('fingerprint', 64);
            $t->timestamps();
            $t->unique(['business_id', 'fingerprint']);
        });
        foreach (['leads', 'clients'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('entry_context')->nullable());
        }
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->json('seo');
            $t->timestamps();
        });
        Schema::create('tracking_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('draft');
            $t->json('settings');
            $t->string('note', 300);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['tracking_versions', 'site_settings', 'campaign_links', 'reels', 'blog_revisions', 'blog_posts'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['leads', 'clients'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('entry_context'));
        }
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn(['seo_title', 'seo_description']));
    }
};
