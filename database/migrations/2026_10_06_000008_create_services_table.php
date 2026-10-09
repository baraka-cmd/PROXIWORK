<?php

declare(strict_types=1);

use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('short_description', 300)->nullable();
            $table->text('description');
            $table->string('pricing_type', 20)->default(ServicePricingType::QUOTE->value);
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_min', 12, 2)->nullable();
            $table->decimal('price_max', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->string('status', 20)->default(ServiceStatus::DRAFT->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['professional_profile_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
