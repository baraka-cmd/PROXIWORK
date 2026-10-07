<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('moderator_id')->constrained('users')->restrictOnDelete();
            $table->string('action_type', 64);
            $table->string('reason_code', 64)->nullable();
            $table->text('note')->nullable();
            $table->string('target_type', 100);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->index(['report_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->unique(['report_id', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
    }
};
