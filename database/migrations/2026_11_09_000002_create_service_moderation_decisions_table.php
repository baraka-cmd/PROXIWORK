<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_moderation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'created_at'], 'service_moderation_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_moderation_decisions');
    }
};
