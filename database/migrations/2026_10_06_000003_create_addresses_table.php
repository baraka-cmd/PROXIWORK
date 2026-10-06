<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label', 100);
            $table->string('recipient_name', 255)->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->char('country_code', 2);
            $table->string('province', 100)->nullable();
            $table->string('city', 100);
            $table->string('commune', 100)->nullable();
            $table->string('neighborhood', 150)->nullable();

            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->string('postal_code', 32)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
