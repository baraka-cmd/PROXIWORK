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
        if (! Schema::hasTable('favorites')
            || ! Schema::hasColumn('favorites', 'professional_profile_id')
            || ! Schema::hasTable('services')) {
            throw new RuntimeException('Les tables favorites, professional_profiles et services doivent exister avant cette migration.');
        }

        if (! Schema::hasColumn('favorites', 'service_id')) {
            Schema::table('favorites', function (Blueprint $table): void {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('professional_profile_id')
                    ->constrained('services')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('favorites', function (Blueprint $table): void {
            $table->unsignedBigInteger('professional_profile_id')->nullable()->change();
        });

        if (! $this->indexExists('favorites', 'favorites_user_service_unique')) {
            Schema::table('favorites', function (Blueprint $table): void {
                $table->unique(['user_id', 'service_id'], 'favorites_user_service_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('favorites') || ! Schema::hasColumn('favorites', 'service_id')) {
            return;
        }

        DB::table('favorites')->whereNotNull('service_id')->delete();

        Schema::table('favorites', function (Blueprint $table): void {
            $table->dropUnique('favorites_user_service_unique');
            $table->dropConstrainedForeignId('service_id');
            $table->unsignedBigInteger('professional_profile_id')->nullable(false)->change();
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(static fn (object $row): bool => $row->name === $index);
        }

        return collect(DB::select('SHOW INDEX FROM '.$table))
            ->contains(static fn (object $row): bool => $row->Key_name === $index);
    }
};
