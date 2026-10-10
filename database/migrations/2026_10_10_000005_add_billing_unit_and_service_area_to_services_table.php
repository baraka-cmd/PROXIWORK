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
            $table->string('billing_unit', 20)->nullable()->after('currency');
            $table->string('service_area', 255)->nullable()->after('estimated_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['billing_unit', 'service_area']);
        });
    }
};
