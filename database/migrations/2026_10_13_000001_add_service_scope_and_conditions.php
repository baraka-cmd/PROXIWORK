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
            $table->text('included_items')->nullable()->after('description');
            $table->text('excluded_items')->nullable()->after('included_items');
            $table->text('client_requirements')->nullable()->after('excluded_items');
            $table->text('deliverables')->nullable()->after('client_requirements');
            $table->text('commercial_conditions')->nullable()->after('deliverables');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn([
                'included_items',
                'excluded_items',
                'client_requirements',
                'deliverables',
                'commercial_conditions',
            ]);
        });
    }
};
