<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_worker_heartbeats', function (Blueprint $table): void {
            $table->string('worker_id', 64)->primary();
            $table->string('hostname', 191);
            $table->unsignedBigInteger('process_id');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_worker_heartbeats');
    }
};
