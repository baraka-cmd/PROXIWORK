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

        // MySQL enforces that each favorite points to exactly one target.
        // SQLite test databases rely on the same invariant enforced by FavoriteService.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE favorites ADD CONSTRAINT favorites_exactly_one_target_check '
                .'CHECK ((professional_profile_id IS NOT NULL AND service_id IS NULL) '
                .'OR (professional_profile_id IS NULL AND service_id IS NOT NULL))'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('favorites') || ! Schema::hasColumn('favorites', 'service_id')) {
            return;
        }

        // The old schema cannot represent service favorites, so rollback removes those rows.
        DB::table('favorites')->whereNotNull('service_id')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE favorites DROP CHECK favorites_exactly_one_target_check');
        }

        Schema::table('favorites', function (Blueprint $table): void {
            if ($this->indexExists('favorites', 'favorites_user_service_unique')) {
                $table->dropUnique('favorites_user_service_unique');
            }

            $table->dropConstrainedForeignId('service_id');
            $table->unsignedBigInteger('professional_profile_id')->nullable(false)->change();
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
