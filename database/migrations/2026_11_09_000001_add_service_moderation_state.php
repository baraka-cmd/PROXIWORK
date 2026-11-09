<?php

declare(strict_types=1);

use App\Enums\ServiceModerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('moderation_status', 30)->default(ServiceModerationStatus::CLEAR->value)->after('status');
            $table->text('moderation_reason')->nullable()->after('moderation_status');
            $table->foreignId('moderated_by')->nullable()->after('moderation_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->index(['moderation_status', 'status'], 'services_moderation_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_moderation_status_idx');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['moderation_status', 'moderation_reason', 'moderated_at']);
        });
    }
};
