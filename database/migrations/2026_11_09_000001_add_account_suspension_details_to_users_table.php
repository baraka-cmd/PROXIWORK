<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('suspended_until')->nullable()->index();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 60)->nullable();
            $table->text('suspension_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['suspended_by']);
            $table->dropIndex(['suspended_until']);
            $table->dropColumn([
                'suspended_at',
                'suspended_until',
                'suspended_by',
                'suspension_reason',
                'suspension_note',
            ]);
        });
    }
};
