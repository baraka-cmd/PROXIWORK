<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            // Older ledger rows cannot be assigned a bucket reliably after the
            // fact. Keep them nullable; every new WalletService write supplies it.
            $table->string('balance_bucket', 32)->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropColumn('balance_bucket');
        });
    }
};
