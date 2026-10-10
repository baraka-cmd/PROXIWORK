<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_skill', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'skill_id']);
            $table->index(['skill_id', 'category_id']);
        });

        Schema::create('professional_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['professional_profile_id', 'category_id'], 'pro_categories_unique');
            $table->index(['category_id', 'professional_profile_id'], 'pro_categories_category_profile_idx');
        });

        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->string('business_name', 160)->nullable()->after('user_id');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->string('billing_unit', 50)->nullable();
            $table->string('service_area', 255)->nullable();
            $table->text('conditions')->nullable();
        });

        Schema::create('professional_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('review_status', 20)->default('pending');
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['professional_profile_id', 'review_status'], 'pro_docs_profile_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_documents');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['billing_unit', 'service_area', 'conditions']);
        });

        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->dropColumn('business_name');
        });

        Schema::dropIfExists('professional_categories');
        Schema::dropIfExists('category_skill');
    }
};
