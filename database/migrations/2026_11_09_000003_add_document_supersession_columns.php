<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_documents', function (Blueprint $table): void {
            $table->foreignId('superseded_by_document_id')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('professional_documents')
                ->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('superseded_by_document_id');

            $table->index(
                ['professional_profile_id', 'document_type', 'superseded_by_document_id'],
                'prof_docs_current_revision_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('professional_documents', function (Blueprint $table): void {
            $table->dropIndex('prof_docs_current_revision_idx');
            $table->dropConstrainedForeignId('superseded_by_document_id');
            $table->dropColumn('superseded_at');
        });
    }
};
