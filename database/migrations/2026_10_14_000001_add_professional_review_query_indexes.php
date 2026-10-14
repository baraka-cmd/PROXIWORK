<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->index(
                ['professional_id', 'status', 'published_at'],
                'reviews_professional_status_published_idx',
            );
            $table->index(
                ['professional_id', 'status', 'rating', 'published_at'],
                'reviews_professional_status_rating_published_idx',
            );
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->index(
                ['reporter_id', 'target_type', 'target_id', 'status'],
                'reports_reporter_target_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_reporter_target_status_idx');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('reviews_professional_status_rating_published_idx');
            $table->dropIndex('reviews_professional_status_published_idx');
        });
    }
};
