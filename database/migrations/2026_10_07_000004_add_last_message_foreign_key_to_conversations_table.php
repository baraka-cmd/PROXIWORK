<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('last_message_id')
                ->nullable()
                ->after('order_id')
                ->constrained('messages')
                ->nullOnDelete();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropForeign(['last_message_id']);
            $table->dropIndex(['status', 'updated_at']);
            $table->dropColumn('last_message_id');
        });
    }
};
