<?php

declare(strict_types=1);

use App\Enums\CommissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->string('currency', 3);
            $table->string('calculation_type', 32)->default('percentage');
            $table->string('status', 32)->default(CommissionStatus::POSTED->value);
            $table->timestamp('posted_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->timestamps();

            $table->index(['professional_id', 'currency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
