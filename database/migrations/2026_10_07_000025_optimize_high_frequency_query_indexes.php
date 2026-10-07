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
            $table->dropIndex('users_account_status_index');
            $table->index(
                ['account_status', 'created_at'],
                'users_account_status_created_at_index'
            );
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropIndex('addresses_user_id_is_default_index');
            $table->index(
                ['user_id', 'is_default', 'city', 'province'],
                'addresses_user_default_location_index'
            );
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_professional_profile_id_status_index');
            $table->index(
                ['professional_profile_id', 'status', 'published_at'],
                'services_professional_status_published_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_professional_status_published_index');
            $table->index(
                ['professional_profile_id', 'status'],
                'services_professional_profile_id_status_index'
            );
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropIndex('addresses_user_default_location_index');
            $table->index(
                ['user_id', 'is_default'],
                'addresses_user_id_is_default_index'
            );
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_account_status_created_at_index');
            $table->index(
                ['account_status', 'created_at'],
                'users_account_status_index'
            );
        });
    }
};
