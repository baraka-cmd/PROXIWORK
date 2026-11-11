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
        Schema::create('currencies', function (Blueprint $table): void {
            $table->string('code', 3)->primary();
            $table->string('name', 80);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('currencies')->insert([
            ['code' => 'USD', 'name' => 'Dollar américain', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CDF', 'name' => 'Franc congolais', 'symbol' => 'FC', 'decimal_places' => 2, 'is_active' => true, 'is_default' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
