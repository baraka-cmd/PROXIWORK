<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('category', 40)->default('general')->after('action');
            $table->string('outcome', 24)->default('success')->after('category');
            $table->string('severity', 16)->default('info')->after('outcome');
            $table->uuid('correlation_id')->nullable()->after('severity');
            $table->string('reason', 1000)->nullable()->after('correlation_id');
            $table->string('identifier_hash', 64)->nullable()->after('ip_address');
            $table->json('changes')->nullable()->after('reason');
            $table->index(['category', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index('correlation_id');
            $table->index(['category', 'action', 'ip_address', 'created_at'], 'audit_logs_security_detection_index');
            $table->index(['category', 'action', 'identifier_hash', 'created_at'], 'audit_logs_identifier_detection_index');
        });

        // Classify existing events without fabricating missing history.
        DB::table('audit_logs')
            ->where('action', 'like', 'admin.%')
            ->update(['category' => 'administrative']);
        DB::table('audit_logs')
            ->where('action', 'like', 'admin.auth.%')
            ->update(['category' => 'authentication']);
        DB::table('audit_logs')
            ->where(function ($query): void {
                $query->where('action', 'like', 'auth.%')
                    ->orWhereIn('action', ['login', 'logout', 'password_changed', 'password_reset']);
            })
            ->update(['category' => 'authentication']);
        DB::table('audit_logs')
            ->where('action', 'like', 'security.%')
            ->update(['category' => 'security']);

        Schema::create('security_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->string('rule_key', 100);
            $table->string('fingerprint', 64)->unique();
            $table->string('severity', 16)->default('medium');
            $table->string('status', 24)->default('new');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->json('context')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'last_seen_at']);
            $table->index(['user_id', 'last_seen_at']);
        });

        Schema::create('security_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guard', 20);
            $table->string('identifier_hash', 64);
            $table->text('identifier_ciphertext')->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('device_name', 160)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['guard', 'identifier_hash']);
            $table->index(['user_id', 'revoked_at', 'last_seen_at']);
            $table->index(['expires_at', 'revoked_at']);
        });

        Schema::create('sensitive_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->string('domain', 60);
            $table->string('action', 120);
            $table->string('subject_type', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('result', 24)->default('succeeded');
            $table->string('reason_hash', 64)->nullable();
            $table->text('reason')->nullable();
            $table->decimal('amount', 20, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('changes')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['domain', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_interventions');
        Schema::dropIfExists('security_sessions');
        Schema::dropIfExists('security_alerts');

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['category', 'created_at']);
            $table->dropIndex(['outcome', 'created_at']);
            $table->dropIndex(['correlation_id']);
            $table->dropIndex('audit_logs_security_detection_index');
            $table->dropIndex('audit_logs_identifier_detection_index');
            $table->dropColumn(['category', 'outcome', 'severity', 'correlation_id', 'reason', 'identifier_hash', 'changes']);
        });
    }
};
