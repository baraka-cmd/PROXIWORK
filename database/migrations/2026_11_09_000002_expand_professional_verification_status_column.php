<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            // additional_documents_requested is longer than the original
            // 20-character state values.
            $table->string('verification_status', 40)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('pending')->change();
        });
    }
};
