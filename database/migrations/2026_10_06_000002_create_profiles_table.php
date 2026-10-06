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
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path', 500)->nullable();
            $table->string('locale', 10)->default('fr');
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();
        });

        $now = now();

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $user) use ($now): void {
                DB::table('profiles')->insert([
                    'user_id' => $user->id,
                    'locale' => 'fr',
                    'timezone' => 'UTC',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
