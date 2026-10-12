<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('status', 24)->default('PENDING');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->string('last_error_code', 191)->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'channel'], 'notification_deliveries_notification_channel_unique');
            $table->index(['status', 'available_at'], 'notification_deliveries_status_available_idx');
            $table->index(['user_id', 'created_at'], 'notification_deliveries_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
