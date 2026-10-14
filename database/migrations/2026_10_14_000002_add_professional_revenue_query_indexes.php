<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table): void {
            $table->index(
                ['professional_id', 'posted_at', 'status', 'currency'],
                'commissions_professional_posted_status_currency_idx'
            );
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(
                ['professional_id', 'status', 'completed_at', 'currency'],
                'orders_professional_status_completed_currency_idx'
            );
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'paid_at'], 'payments_status_paid_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_paid_at_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_professional_status_completed_currency_idx');
        });

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropIndex('commissions_professional_posted_status_currency_idx');
        });
    }
};
