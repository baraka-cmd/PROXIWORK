<?php

declare(strict_types=1);

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            $table->index(
                ['verification_status', 'availability_status'],
                'pp_verif_avail_idx'
            );
            $table->index(
                ['rating_average', 'rating_count'],
                'pp_rating_count_idx'
            );
            $table->index('professional_title', 'pp_title_idx');
        });
    }

    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->dropIndex('pp_verif_avail_idx');
            $table->dropIndex('pp_rating_count_idx');
            $table->dropIndex('pp_title_idx');
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
