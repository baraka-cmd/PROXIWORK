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
        ];
    }
}
