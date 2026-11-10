<?php

declare(strict_types=1);

use App\Enums\OrderDisputePriority;
use App\Enums\OrderDisputeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code', 64);
            $table->text('description');
            $table->string('priority', 16)->default(OrderDisputePriority::NORMAL->value);
            $table->string('status', 32)->default(OrderDisputeStatus::OPEN->value);
            $table->string('resolution_code', 64)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'updated_at']);
            $table->index(['assigned_to', 'status']);
            $table->unique('order_id');
            $table->index(['client_id', 'created_at']);
            $table->index(['professional_id', 'created_at']);
        });

        Schema::create('order_dispute_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_dispute_id')->constrained('order_disputes')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_dispute_id', 'created_at']);
        });

        Schema::create('order_dispute_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_dispute_id')->constrained('order_disputes')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->string('visibility', 16)->default('participants');
            $table->timestamps();
            $table->index(['order_dispute_id', 'visibility', 'created_at'], 'odm_visibility_created_idx');
        });

        Schema::create('order_dispute_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_dispute_id')->constrained('order_disputes')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['order_dispute_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_dispute_attachments');
        Schema::dropIfExists('order_dispute_messages');
        Schema::dropIfExists('order_dispute_events');
        Schema::dropIfExists('order_disputes');
    }
};
