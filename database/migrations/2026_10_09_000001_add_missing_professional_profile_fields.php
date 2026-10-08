<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            // Keep fields nullable so existing profiles remain valid during rollout.
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->decimal('starting_price', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('commune', 100)->nullable();
            $table->unsignedSmallInteger('service_radius_km')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');
            $table->timestamp('verified_at')->nullable();

            $table->index(['status', 'visibility']);
            $table->index(['city', 'status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table): void {
            $table->dropIndex('professional_profiles_status_visibility_index');
            $table->dropIndex('professional_profiles_city_status_visibility_index');
            $table->dropColumn([
                'description',
                'years_experience',
                'starting_price',
                'currency',
                'province',
                'city',
                'commune',
                'service_radius_km',
                'latitude',
                'longitude',
                'status',
                'visibility',
                'verified_at',
            ]);
        });
    }
};
