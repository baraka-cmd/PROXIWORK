<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professional_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('disk', 32)->default('local');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('status', 20)->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['professional_profile_id', 'document_type', 'status'], 'prof_docs_profile_type_status_idx');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_documents');
    }
};
