<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->nullable()->unique();
            $table->foreignId('service_request_id')->unique()->constrained('service_requests')->restrictOnDelete();
            $table->foreignId('quotation_id')->unique()->constrained('quotations')->restrictOnDelete();
            $table->foreignId('accepted_offer_id')->unique()->constrained('quotation_offers')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->string('status', 32)->default(OrderStatus::PENDING_PAYMENT->value);
            $table->char('currency', 3);
            $table->decimal('subtotal', 15, 2)->unsigned();
            $table->decimal('total', 15, 2)->unsigned();
            $table->timestamp('accepted_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['professional_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
