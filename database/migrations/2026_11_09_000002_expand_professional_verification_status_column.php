<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 40)->default('pending')->change();
        });

        Schema::table('professional_verification_reviews', function (Blueprint $table): void {
            $table->string('from_status', 40)->change();
            $table->string('to_status', 40)->change();
        });
    }

    public function down(): void
    {
        $hasLongProfileStatus = DB::table('professional_profiles')
            ->whereRaw('CHAR_LENGTH(verification_status) > 20')
            ->exists();

        $hasLongHistoryStatus = DB::table('professional_verification_reviews')
            ->whereRaw('CHAR_LENGTH(from_status) > 20')
            ->orWhereRaw('CHAR_LENGTH(to_status) > 20')
            ->exists();

        if ($hasLongProfileStatus || $hasLongHistoryStatus) {
            throw new RuntimeException('Cannot shrink verification status columns while additional-document requests remain in the database.');
        }

        Schema::table('professional_verification_reviews', function (Blueprint $table): void {
            $table->string('from_status', 20)->change();
            $table->string('to_status', 20)->change();
        });

        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('pending')->change();
        });
    }
};
