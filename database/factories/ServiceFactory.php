<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'professional_profile_id' => ProfessionalProfile::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'pricing_type' => ServicePricingType::QUOTE->value,
            'price' => null,
            'price_min' => null,
            'price_max' => null,
            'currency' => null,
            'estimated_duration_minutes' => fake()->numberBetween(30, 1440),
            'status' => ServiceStatus::DRAFT->value,
            'sort_order' => 0,
            'published_at' => null,
        ];
    }
}
