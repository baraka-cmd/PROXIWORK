<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_address_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('recipient_name', 255);
            $table->string('contact_phone', 50);
            $table->string('country_code', 2);
            $table->string('province', 150);
            $table->string('city', 150);
            $table->string('commune', 150)->nullable();
            $table->string('neighborhood', 150)->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('additional_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_address_snapshots');
    }
};
