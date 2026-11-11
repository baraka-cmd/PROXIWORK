<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_integrity_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 24);
            $table->unsignedBigInteger('records_checked')->default(0);
            $table->unsignedBigInteger('missing_files')->default(0);
            $table->unsignedBigInteger('check_errors')->default(0);
            $table->json('table_results')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_integrity_runs');
    }
};
