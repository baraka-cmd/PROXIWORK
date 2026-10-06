<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'skill_id']);
            $table->index(['skill_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_skills');
    }
};
