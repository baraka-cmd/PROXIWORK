<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id', 191)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('idempotency_key', 128);
            $table->string('status', 32)->default(PaymentStatus::INITIATED->value);
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2)->unsigned();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'idempotency_key']);
            $table->unique(['provider', 'provider_transaction_id']);
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['payment_id', 'status']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
