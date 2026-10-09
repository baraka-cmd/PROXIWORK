<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Compatibility placeholder.
 *
 * This migration originally created favorites before professional_profiles,
 * which fails on a fresh MySQL database. Keep the filename so databases that
 * already recorded the historical migration can upgrade safely. The table is
 * now created by 2026_10_06_000011_create_favorites_table.php, after its
 * referenced table exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty; see the later, correctly ordered migration.
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
