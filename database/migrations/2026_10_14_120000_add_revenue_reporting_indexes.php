<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The commissions, payments and orders reporting indexes are already
        // defined by 2026_10_14_000002_add_professional_revenue_query_indexes.
        // Only the confirmed-refund reporting path needs an additional index.
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->index(
                ['status', 'processed_at', 'payment_id'],
                'payment_refunds_revenue_reporting_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropIndex('payment_refunds_revenue_reporting_idx');
        });
    }
};
