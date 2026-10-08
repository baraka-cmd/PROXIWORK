<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        $service = Service::factory()->create();

        return [
            'client_id' => User::factory(),
            'professional_id' => $service->professional_profile_id,
            'service_id' => $service->getKey(),
            'address_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(2),
            'budget_min' => null,
            'budget_max' => null,
            'currency' => null,
            'desired_at' => now()->addDays(3),
            'status' => ServiceRequestStatus::DRAFT,
            'requested_at' => null,
        ];
    }
}
