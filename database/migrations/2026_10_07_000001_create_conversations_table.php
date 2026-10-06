<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32)->default(ConversationType::CLIENT_PROFESSIONAL->value);
            $table->string('status', 32)->default(ConversationStatus::OPEN->value);
            $table->foreignId('client_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('professional_profiles')->restrictOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['type', 'client_id', 'professional_id'],
                'conversations_client_professional_unique'
            );
            $table->index(['client_id', 'updated_at']);
            $table->index(['professional_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
