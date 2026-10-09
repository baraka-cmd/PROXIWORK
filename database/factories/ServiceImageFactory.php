<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceImageFactory extends Factory
{
    protected $model = ServiceImage::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'path' => 'services/example/image.webp',
            'alt_text' => fake()->sentence(4),
            'sort_order' => 0,
            'is_cover' => true,
        ];
    }
}
