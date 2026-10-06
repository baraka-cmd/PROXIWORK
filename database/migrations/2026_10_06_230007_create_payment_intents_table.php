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
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->string('request_fingerprint', 64);
            $table->string('method', 32);
            $table->string('provider', 32);
            $table->string('status', 32)->default(PaymentStatus::INITIATED->value);
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2)->unsigned();
            $table->string('provider_reference', 191)->nullable()->unique();
            $table->string('redirect_url', 2048)->nullable();
            $table->text('instructions')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'idempotency_key']);
            $table->index(['order_id', 'status']);
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
