<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index('created_at', 'payments_admin_finance_created_at_idx');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->index('created_at', 'payment_transactions_admin_finance_created_at_idx');
        });

        Schema::table('commissions', function (Blueprint $table): void {
            $table->index('posted_at', 'commissions_admin_finance_posted_at_idx');
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->index('updated_at', 'wallets_admin_finance_updated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropIndex('wallets_admin_finance_updated_at_idx');
        });

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropIndex('commissions_admin_finance_posted_at_idx');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropIndex('payment_transactions_admin_finance_created_at_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_admin_finance_created_at_idx');
        });
    }
};
