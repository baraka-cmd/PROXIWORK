<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->text('response');
            $table->string('status', 32)->default(ReviewStatus::PUBLISHED->value);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            $table->timestamps();

            $table->index(['professional_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_responses');
    }
};
