<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('read_at');
            $table->index(
                ['notifiable_type', 'notifiable_id', 'archived_at', 'read_at'],
                'notifications_owner_archive_read_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_owner_archive_read_idx');
            $table->dropColumn('archived_at');
        });
    }
};
