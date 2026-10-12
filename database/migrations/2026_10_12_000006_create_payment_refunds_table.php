<?php

declare(strict_types=1);

use App\Enums\PaymentRefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2)->unsigned();
            $table->char('currency', 3);
            $table->string('status', 32)->default(PaymentRefundStatus::REQUESTED->value);
            $table->text('reason');
            $table->text('review_note')->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status', 'created_at']);
            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
