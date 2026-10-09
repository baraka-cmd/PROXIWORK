<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('available_balance', 20, 2)->unsigned()->default(0);
            $table->decimal('pending_balance', 20, 2)->unsigned()->default(0);
            $table->decimal('locked_balance', 20, 2)->unsigned()->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['professional_id', 'currency']);
            $table->index(['professional_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
