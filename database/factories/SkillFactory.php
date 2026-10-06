<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'description' => fake()->optional()->sentence(),
            'icon' => null,
            'status' => 'active',
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
