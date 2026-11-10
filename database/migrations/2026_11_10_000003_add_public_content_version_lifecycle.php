<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_page_versions', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->after('content');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index(['cms_page_id', 'status'], 'cms_page_versions_status_idx');
        });

        Schema::table('faq_article_versions', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->after('content');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index(['faq_article_id', 'status'], 'faq_article_versions_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('faq_article_versions', function (Blueprint $table): void {
            $table->dropIndex('faq_article_versions_status_idx');
            $table->dropColumn(['status', 'published_at']);
        });

        Schema::table('cms_page_versions', function (Blueprint $table): void {
            $table->dropIndex('cms_page_versions_status_idx');
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
