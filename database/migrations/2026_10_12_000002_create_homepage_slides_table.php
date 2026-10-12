<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_slides', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('subtitle');
            $table->string('image_path', 500)->nullable();
            $table->string('mobile_image_path', 500)->nullable();
            $table->string('image_alt', 255)->nullable();
            $table->string('primary_label', 80);
            $table->string('primary_route', 255);
            $table->string('secondary_label', 80)->nullable();
            $table->string('secondary_route', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_slides');
    }
};