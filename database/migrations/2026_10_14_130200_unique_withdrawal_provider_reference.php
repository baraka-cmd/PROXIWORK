<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The database constraint is the final guard against two concurrent
        // settlements reusing the same external reference for the same channel.
        // If historical duplicates exist, the migration must stop and require
        // manual reconciliation before the constraint can be installed.
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->unique(
                ['provider', 'provider_transaction_id'],
                'withdrawals_provider_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropUnique('withdrawals_provider_reference_unique');
        });
    }
};
