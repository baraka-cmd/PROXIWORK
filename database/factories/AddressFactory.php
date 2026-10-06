<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Maison', 'Bureau', 'Atelier']),
            'recipient_name' => fake()->name(),
            'contact_phone' => fake()->numerify('+243#########'),
            'country_code' => 'CD',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'commune' => fake()->randomElement(['Karisimbi', 'Goma']),
            'neighborhood' => fake()->randomElement(['Ndosho', 'Katindo', 'Kyeshero']),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'landmark' => fake()->optional()->sentence(4),
            'postal_code' => null,
            'latitude' => null,
            'longitude' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }
}
