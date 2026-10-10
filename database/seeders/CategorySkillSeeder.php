<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySkillSeeder extends Seeder
{
    /**
     * Connect the initial catalogue skills to their service categories.
     *
     * The administration can add/change these relationships later; this
     * seed only provides a useful, repeatable starting catalogue.
     */
    public function run(): void
    {
        $mapping = [
            'laravel' => 'developpement-web',
            'flutter' => 'developpement-mobile',
            'networking' => 'reseaux-infrastructure',
            'electricity' => 'electricite',
            'plumbing' => 'plomberie',
            'uiux' => 'ui-ux-design',
            'database' => 'bases-de-donnees',
        ];

        foreach ($mapping as $skillSlug => $categorySlug) {
            $skillId = DB::table('skills')->where('slug', $skillSlug)->value('id');
            $categoryId = DB::table('categories')->where('slug', $categorySlug)->value('id');

            if (! $skillId || ! $categoryId) {
                continue;
            }

            DB::table('category_skill')->updateOrInsert(
                ['category_id' => $categoryId, 'skill_id' => $skillId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
