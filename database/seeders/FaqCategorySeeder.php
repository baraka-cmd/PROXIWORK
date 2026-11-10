<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Compte et inscription', 'compte-inscription', 10],
            ['Espace client', 'espace-client', 20],
            ['Espace professionnel', 'espace-professionnel', 30],
            ['Paiements et retraits', 'paiements-retraits', 40],
            ['Sécurité et confiance', 'securite-confiance', 50],
            ['Assistance', 'assistance', 60],
        ] as [$name, $slug, $order]) {
            FaqCategory::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }
}
