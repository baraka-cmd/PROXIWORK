<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_incidents', function (Blueprint $table): void {
            $table->string('fingerprint', 64)->primary();
            $table->string('exception_type', 191);
            $table->string('location_hint', 191);
            $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_incidents');
    }
};
