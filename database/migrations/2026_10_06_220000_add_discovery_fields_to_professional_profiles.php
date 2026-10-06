<?php

declare(strict_types=1);

use AppEnumsProfessionalAvailabilityStatus;
use AppEnumsProfessionalVerificationStatus;
use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->string('professional_title', 160)->nullable()->after('user_id');
            $table->string('verification_status', 20)
                ->default(ProfessionalVerificationStatus::PENDING->value)
                ->after('professional_title');
            $table->string('availability_status', 20)
                ->default(ProfessionalAvailabilityStatus::UNKNOWN->value)
                ->after('verification_status');
            $table->decimal('rating_average', 3, 2)->default(0)->after('availability_status');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_average');

            $table->index(['verification_status', 'availability_status']);
            $table->index(['rating_average', 'rating_count']);
            $table->index('professional_title');
        });
    }

    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->dropIndex(['professional_profiles_verification_status_availability_status_index']);
            $table->dropIndex(['professional_profiles_rating_average_rating_count_index']);
            $table->dropIndex(['professional_profiles_professional_title_index']);
            $table->dropColumn([
                'professional_title',
                'verification_status',
                'availability_status',
                'rating_average',
                'rating_count',
            ]);
        });
    }
};
