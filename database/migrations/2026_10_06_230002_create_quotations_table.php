<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained('service_requests')->restrictOnDelete();
            $table->foreignId('current_offer_id')->nullable();
            $table->foreignId('accepted_offer_id')->nullable();
            $table->string('status', 20)->default(QuotationStatus::SENT->value);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
