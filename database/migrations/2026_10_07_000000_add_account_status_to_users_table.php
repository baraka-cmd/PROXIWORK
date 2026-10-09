<?php

declare(strict_types=1);

use App\Enums\UserAccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status', 20)
                ->default(UserAccountStatus::ACTIVE->value)
                ->after('password');

            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_account_status_index');
            $table->dropColumn('account_status');
        });
    }
};
