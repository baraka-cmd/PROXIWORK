<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_verification_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_profile_id');
            $table->foreign('professional_profile_id', 'pvr_profile_fk')
                ->references('id')
                ->on('professional_profiles')
                ->cascadeOnDelete();

            $table->foreignId('admin_user_id');
            $table->foreign('admin_user_id', 'pvr_admin_user_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('reason_code', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(
                ['professional_profile_id', 'created_at'],
                'pvr_profile_created_idx'
            );
            $table->index(
                ['admin_user_id', 'created_at'],
                'pvr_admin_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_verification_reviews');
    }
};
