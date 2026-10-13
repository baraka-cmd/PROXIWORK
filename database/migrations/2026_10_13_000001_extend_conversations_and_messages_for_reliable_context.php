<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('context_key', 191)->default('general')->after('professional_id');
            $table->dropUnique('conversations_client_professional_unique');
        });

        DB::table('conversations')
            ->select(['id', 'service_request_id', 'order_id'])
            ->orderBy('id')
            ->chunkById(200, function ($conversations): void {
                foreach ($conversations as $conversation) {
                    $contextKey = $conversation->order_id !== null
                        ? 'order:'.$conversation->order_id
                        : ($conversation->service_request_id !== null
                            ? 'request:'.$conversation->service_request_id
                            : 'general');

                    DB::table('conversations')
                        ->where('id', $conversation->id)
                        ->update(['context_key' => $contextKey]);
                }
            });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->unique(
                ['type', 'client_id', 'professional_id', 'context_key'],
                'conversations_context_unique'
            );
            $table->index(['context_key', 'updated_at'], 'conversations_context_activity_idx');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->string('idempotency_key', 36)->nullable()->after('body');
            $table->unique(
                ['conversation_id', 'sender_id', 'idempotency_key'],
                'messages_idempotency_unique'
            );
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->timestamp('pinned_at')->nullable()->after('left_at');
            $table->timestamp('archived_at')->nullable()->after('pinned_at');
            $table->index(['user_id', 'archived_at', 'pinned_at'], 'cp_user_archive_pin_idx');
        });
    }

    public function down(): void
    {
        $hasMultipleContexts = DB::table('conversations')
            ->select(['type', 'client_id', 'professional_id'])
            ->groupBy(['type', 'client_id', 'professional_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleContexts) {
            throw new RuntimeException(
                'Cannot roll back conversation context while multiple contexts exist for the same client and professional. Export and reconcile those conversations first.'
            );
        }

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropIndex('cp_user_archive_pin_idx');
            $table->dropColumn(['pinned_at', 'archived_at']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique('messages_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_context_activity_idx');
            $table->dropUnique('conversations_context_unique');
            $table->dropColumn('context_key');
            $table->unique(
                ['type', 'client_id', 'professional_id'],
                'conversations_client_professional_unique'
            );
        });
    }
};
