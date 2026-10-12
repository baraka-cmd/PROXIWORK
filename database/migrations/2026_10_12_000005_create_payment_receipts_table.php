<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('payment_id')->unique()->constrained('payments')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->unique()->constrained('payment_transactions')->restrictOnDelete();
            $table->string('mode', 20)->default('simulation');
            $table->json('payload');
            $table->char('content_hash', 64);
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['mode', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
