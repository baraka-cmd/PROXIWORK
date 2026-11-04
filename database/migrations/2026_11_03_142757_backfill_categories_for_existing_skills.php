<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $skillCategorySlugs = [
            'laravel' => 'developpement-web',
            'flutter' => 'developpement-mobile',
            'networking' => 'reseaux-infrastructure',
            'electricity' => 'electricite',
            'plumbing' => 'plomberie',
            'uiux' => 'ui-ux-design',
            'database' => 'bases-de-donnees',
        ];

        foreach ($skillCategorySlugs as $skillSlug => $categorySlug) {
            $categoryId = DB::table('categories')->where('slug', $categorySlug)->value('id');

            if ($categoryId !== null) {
                DB::table('skills')
                    ->where('slug', $skillSlug)
                    ->whereNull('category_id')
                    ->update(['category_id' => $categoryId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep category assignments intact so administrator edits are not lost.
    }
};
