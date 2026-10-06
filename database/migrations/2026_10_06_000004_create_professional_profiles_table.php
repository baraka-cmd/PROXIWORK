<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_profiles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('professional_title', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();

            $table->decimal('starting_price', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();

            $table->string('province', 100)->nullable();
            $table->string('city', 100);
            $table->string('commune', 100)->nullable();
            $table->unsignedSmallInteger('service_radius_km')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');
            $table->string('verification_status', 20)->default('unverified');
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index(['status', 'visibility']);
            $table->index(['city', 'status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_profiles');
    }
};
