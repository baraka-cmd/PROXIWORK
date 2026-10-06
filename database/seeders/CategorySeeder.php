<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Informatique & Technologie' => [
                'Développement Web',
                'Développement Mobile',
                'Logiciels & Applications',
                'Réseaux & Infrastructure',
                'Cybersécurité',
                'Bases de données',
                'Support informatique',
            ],
            'Construction & Bâtiment' => [
                'Maçonnerie',
                'Plomberie',
                'Électricité',
                'Peinture & Décoration',
                'Menuiserie',
                'Climatisation',
            ],
            'Réparation & Maintenance' => [
                'Réparation automobile',
                'Réparation de motos',
                'Électronique',
                'Électroménager',
                'Téléphones & Smartphones',
            ],
            'Design & Création' => [
                'Design graphique',
                'UI/UX Design',
                'Photographie',
                'Vidéo & Montage',
                'Animation & Motion Design',
            ],
            'Éducation & Formation' => [
                'Cours particuliers',
                'Langues',
                'Informatique & Programmation',
                'Formation professionnelle',
            ],
            'Conseil & Services professionnels' => [
                'Conseil en gestion',
                'Comptabilité',
                'Marketing & Communication',
                'Rédaction & Traduction',
            ],
            'Transport & Logistique' => [
                'Transport de personnes',
                'Livraison',
                'Déménagement',
                'Transport de marchandises',
            ],
            'Beauté & Bien-être' => [
                'Coiffure',
                'Esthétique',
                'Massage & Bien-être',
            ],
        ];

        foreach ($categories as $rootOrder => $children) {
            $rootName = array_search($children, $categories, true);

            $root = Category::updateOrCreate(
                ['slug' => Str::slug($rootName)],
                [
                    'parent_id' => null,
                    'name' => $rootName,
                    'description' => null,
                    'icon' => null,
                    'image_path' => null,
                    'status' => CategoryStatus::ACTIVE,
                    'is_featured' => $rootOrder < 4,
                    'sort_order' => $rootOrder,
                ],
            );

            foreach ($children as $childOrder => $childName) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'parent_id' => $root->id,
                        'name' => $childName,
                        'description' => null,
                        'icon' => null,
                        'image_path' => null,
                        'status' => CategoryStatus::ACTIVE,
                        'is_featured' => false,
                        'sort_order' => $childOrder,
                    ],
                );
            }
        }
    }
}
