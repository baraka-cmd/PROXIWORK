<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('terms_accepted_at')->nullable()->after('pending_email');
        });

        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->timestamp('professional_terms_accepted_at')->nullable()->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->dropColumn('professional_terms_accepted_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
