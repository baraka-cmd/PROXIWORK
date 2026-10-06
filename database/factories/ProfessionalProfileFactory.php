<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfessionalProfileFactory extends Factory
{
    protected $model = ProfessionalProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'professional_title' => fake()->randomElement([
                'Développeur Web & Mobile',
                'Électricien',
                'Plombier',
                'Designer graphique',
            ]),
            'description' => fake()->paragraph(),
            'years_experience' => fake()->numberBetween(0, 15),
            'starting_price' => fake()->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'commune' => fake()->randomElement(['Karisimbi', 'Goma']),
            'service_radius_km' => fake()->numberBetween(1, 50),
            'latitude' => null,
            'longitude' => null,
            'status' => ProfessionalProfile::STATUS_DRAFT,
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
            'verification_status' => ProfessionalProfile::VERIFICATION_UNVERIFIED,
            'verified_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => ProfessionalProfile::STATUS_ACTIVE,
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (): array => [
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => ProfessionalProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
        ]);
    }
}
