<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
        if (DB::table('professional_profiles')->whereRaw('CHAR_LENGTH(verification_status) > 20')->exists()) {
            throw new RuntimeException('Cannot shrink verification_status while additional-document requests still exist.');
        }

        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('pending')->change();
        });
    }
};
