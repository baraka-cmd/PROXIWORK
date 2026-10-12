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
            $table->string('pending_email')->nullable()->unique();
            $table->string('pending_email_token_hash', 64)->nullable();
            $table->timestamp('pending_email_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['pending_email']);
            $table->dropColumn([
                'pending_email',
                'pending_email_token_hash',
                'pending_email_expires_at',
            ]);
        });
    }
};
