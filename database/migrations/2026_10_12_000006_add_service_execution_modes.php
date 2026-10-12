<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('execution_mode', 32)->default('client_location')->after('description');
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->string('execution_mode', 32)->default('client_location')->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn('execution_mode');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('execution_mode');
        });
    }
};
