<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('actor_type', 20);
            $table->unsignedInteger('version');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->text('description');
            $table->unsignedInteger('duration_value');
            $table->string('duration_unit', 10);
            $table->text('conditions')->nullable();
            $table->timestamp('valid_until');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['quotation_id', 'version']);
            $table->index(['quotation_id', 'created_at']);
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_offers');
    }
};
