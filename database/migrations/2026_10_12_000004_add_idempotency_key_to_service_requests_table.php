<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('status');
            $table->unique(
                ['client_id', 'idempotency_key'],
                'sr_client_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropUnique('sr_client_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
