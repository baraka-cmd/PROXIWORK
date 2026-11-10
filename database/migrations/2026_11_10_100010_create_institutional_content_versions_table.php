<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_content_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institutional_content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['institutional_content_id', 'version'], 'icv_content_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_content_versions');
    }
};
