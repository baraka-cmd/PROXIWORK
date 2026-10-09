<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id');
            $table->foreign('service_request_id', 'srsh_request_fk')
                ->references('id')
                ->on('service_requests')
                ->cascadeOnDelete();

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);

            $table->foreignId('changed_by')->nullable();
            $table->foreign('changed_by', 'srsh_changed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service_request_id', 'created_at'], 'srsh_request_created_idx');
            $table->index(['changed_by', 'created_at'], 'srsh_changed_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_status_histories');
    }
};
