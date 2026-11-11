<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('http_performance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket_at')->unique();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->unsignedBigInteger('duration_total_ms')->default(0);
            $table->unsignedInteger('p95_bucket_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('http_performance_snapshots');
    }
};
