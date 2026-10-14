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
                ['professional_id', 'posted_at', 'currency', 'status'],
                'commissions_revenue_reporting_idx',
            );
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(
                ['status', 'paid_at', 'currency'],
                'payments_revenue_reporting_idx',
            );
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->index(
                ['status', 'processed_at', 'payment_id'],
                'payment_refunds_revenue_reporting_idx',
            );
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(
                ['professional_id', 'status', 'completed_at', 'currency'],
                'orders_revenue_reporting_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_revenue_reporting_idx');
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropIndex('payment_refunds_revenue_reporting_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_revenue_reporting_idx');
        });

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropIndex('commissions_revenue_reporting_idx');
        });
    }
};
