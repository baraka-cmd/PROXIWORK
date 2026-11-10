<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_delivery_id')->constrained('notification_deliveries', 'id', 'nda_delivery_fk')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('channel', 20);
            $table->string('status', 20)->default('PROCESSING');
            $table->string('provider_reference', 191)->nullable();
            $table->string('error_code', 191)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_delivery_id', 'attempt_number'], 'notification_delivery_attempt_unique');
            $table->index(['channel', 'status', 'started_at'], 'notification_delivery_attempt_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
    }
};
