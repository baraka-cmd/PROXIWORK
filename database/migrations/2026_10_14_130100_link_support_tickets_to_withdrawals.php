<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('withdrawal_id')
                ->nullable()
                ->after('user_id')
                ->constrained('withdrawals')
                ->restrictOnDelete();

            $table->index(['withdrawal_id', 'created_at'], 'support_tickets_withdrawal_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_withdrawal_timeline_index');
            $table->dropConstrainedForeignId('withdrawal_id');
        });
    }
};
