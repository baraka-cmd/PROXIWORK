<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_verification_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('reason_code', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['professional_profile_id', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_verification_reviews');
    }
};
