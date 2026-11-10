<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 120);
            $table->string('event_key', 120);
            $table->string('category', 40);
            $table->string('channel', 20)->default('database');
            $table->string('locale', 10)->default('fr');
            $table->string('subject', 180)->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['code', 'channel', 'locale'], 'notification_templates_code_channel_locale_unique');
            $table->index(['status', 'event_key']);
        });

        Schema::create('notification_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_template_id')->constrained('notification_templates', 'id', 'ntv_template_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['notification_template_id', 'version'], 'notification_template_versions_unique');
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->string('type', 30)->default('information');
            $table->string('placement', 30)->default('announcements');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_pinned')->default(false);
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'placement', 'starts_at', 'ends_at'], 'announcements_visibility_idx');
        });

        Schema::create('communication_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('subject', 180);
            $table->text('body');
            $table->string('channel', 20)->default('database');
            $table->json('audience_rules');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('communication_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('communication_campaigns', 'id', 'ccr_campaign_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('error_code', 120)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id'], 'campaign_recipients_campaign_user_unique');
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('draft');
            $table->string('seo_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image_path', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('cms_page_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cms_page_id', 'version'], 'cms_page_versions_unique');
        });

        Schema::create('faq_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('faq_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faq_category_id')->constrained()->restrictOnDelete();
            $table->string('question', 255);
            $table->string('slug', 200)->unique();
            $table->longText('answer');
            $table->string('audience', 20)->default('all');
            $table->string('locale', 10)->default('fr');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('helpful_yes')->default(0);
            $table->unsignedInteger('helpful_no')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'audience', 'locale', 'sort_order'], 'faq_articles_publication_idx');
        });

        Schema::create('institutional_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->string('document_type', 40);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_version')->default(0);
            $table->boolean('requires_acceptance')->default(false);
            $table->timestamps();
            $table->index(['status', 'document_type']);
        });

        Schema::create('institutional_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institutional_document_id')->constrained('institutional_documents', 'id', 'idv_document_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->string('change_reason', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['institutional_document_id', 'version'], 'institutional_document_versions_unique');
        });

        Schema::create('institutional_document_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institutional_document_version_id')->constrained('institutional_document_versions', 'id', 'ida_version_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->string('source', 30)->default('web');
            $table->timestamps();
            $table->unique(['institutional_document_version_id', 'user_id'], 'institutional_document_acceptances_unique');
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 40)->default('public');
            $table->string('path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['mime_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('institutional_document_acceptances');
        Schema::dropIfExists('institutional_document_versions');
        Schema::dropIfExists('institutional_documents');
        Schema::dropIfExists('faq_articles');
        Schema::dropIfExists('faq_categories');
        Schema::dropIfExists('cms_page_versions');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('communication_campaign_recipients');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notification_template_versions');
        Schema::dropIfExists('notification_templates');
    }
};
