<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_page_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['public_page_id', 'version'], 'ppv_page_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_page_versions');
    }
};
