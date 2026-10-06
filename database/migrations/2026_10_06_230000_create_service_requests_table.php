<?php

declare(strict_types=1);

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('desired_at')->nullable();
            $table->string('status', 20)->default(ServiceRequestStatus::DRAFT->value);
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status', 'created_at']);
            $table->index(['professional_id', 'status', 'created_at']);
            $table->index(['service_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
