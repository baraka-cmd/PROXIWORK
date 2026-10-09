<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('service_title', 255);
            $table->text('service_description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->unsigned();
            $table->decimal('subtotal', 15, 2)->unsigned();
            $table->char('currency', 3);
            $table->unsignedInteger('duration_value');
            $table->string('duration_unit', 20);
            $table->text('conditions')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
