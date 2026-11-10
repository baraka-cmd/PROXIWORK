<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements', 'id', 'av_announcement_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['announcement_id', 'version'], 'announcement_versions_unique');
        });

        Schema::create('faq_article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faq_article_id')->constrained('faq_articles', 'id', 'fav_article_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['faq_article_id', 'version'], 'faq_article_versions_unique');
        });

        Schema::create('communication_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->json('rules');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_by');
        });

        Schema::create('content_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faq_article_id')->constrained('faq_articles', 'id', 'cf_faq_article_fk')->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->boolean('helpful');
            $table->timestamps();
            $table->unique(['faq_article_id', 'fingerprint'], 'content_feedback_article_fingerprint_unique');
        });

        Schema::table('communication_campaigns', function (Blueprint $table): void {
            $table->foreignId('communication_segment_id')->nullable()
                ->constrained('communication_segments', 'id', 'campaign_segment_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('communication_campaigns', function (Blueprint $table): void {
            $table->dropForeign('campaign_segment_fk');
            $table->dropColumn('communication_segment_id');
        });

        Schema::dropIfExists('content_feedback');
        Schema::dropIfExists('communication_segments');
        Schema::dropIfExists('faq_article_versions');
        Schema::dropIfExists('announcement_versions');
    }
};
