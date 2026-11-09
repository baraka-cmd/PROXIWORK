<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_document_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_document_id')
                ->constrained('professional_documents')
                ->cascadeOnDelete();
            $table->foreignId('admin_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['professional_document_id', 'created_at'], 'pdr_document_created_idx');
            $table->index(['admin_user_id', 'created_at'], 'pdr_admin_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_document_reviews');
    }
};
