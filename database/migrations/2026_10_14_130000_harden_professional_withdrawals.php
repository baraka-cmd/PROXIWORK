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
            // Historical before/after values were written against different wallet
            // buckets. "unknown" is intentional; old rows must not be reinterpreted.
            $table->string('balance_bucket', 16)->default('unknown')->after('balance_after');
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            // New destinations are encrypted by Laravel's encrypted cast. The
            // legacy destination column remains for backward-compatible reads and
            // stores only a masked placeholder for new requests.
            $table->text('destination_ciphertext')->nullable()->after('destination');
            $table->decimal('fee_amount', 20, 2)->unsigned()->nullable()->after('amount');
            $table->decimal('net_amount', 20, 2)->unsigned()->nullable()->after('fee_amount');
            $table->timestamp('review_required_at')->nullable()->after('completed_at');
            $table->foreignId('reviewed_by')->nullable()->after('review_required_at')
                ->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable()->after('reviewed_by');
        });

        Schema::create('withdrawal_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('withdrawal_id')->constrained('withdrawals')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 32)->default('system');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();

            $table->unique(['withdrawal_id', 'idempotency_key'], 'withdrawal_history_idempotency_unique');
            $table->index(['withdrawal_id', 'created_at'], 'withdrawal_history_timeline_index');
            $table->index(['to_status', 'created_at'], 'withdrawal_history_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_status_histories');

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'destination_ciphertext',
                'fee_amount',
                'net_amount',
                'review_required_at',
                'review_reason',
            ]);
        });

        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropColumn('balance_bucket');
        });
    }
};
