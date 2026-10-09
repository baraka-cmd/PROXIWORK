<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();
            $table->foreignId('skill_id')
                ->constrained('skills')
                ->restrictOnDelete();
            $table->string('proficiency_level', 20)->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->timestamps();

            $table->unique(['professional_profile_id', 'skill_id']);
            $table->index(['skill_id', 'professional_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_skills');
    }
};
