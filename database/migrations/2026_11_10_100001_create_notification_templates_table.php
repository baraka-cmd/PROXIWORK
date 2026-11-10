<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100);
            $table->string('name', 160);
            $table->string('channel', 24)->default('database');
            $table->string('locale', 12)->default('fr');
            $table->string('subject', 180)->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'channel', 'locale'], 'nt_key_channel_locale_unique');
            $table->index(['status', 'channel', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
