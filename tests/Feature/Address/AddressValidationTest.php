<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_validation_rejects_invalid_phone_country_and_coordinates(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/addresses', [
            'label' => str_repeat('A', 101),
            'contact_phone' => 'not-a-phone',
            'country_code' => 'COD',
            'city' => 'Goma',
            'address_line_1' => 'Avenue',
            'latitude' => 91,
            'longitude' => 181,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'label',
                'contact_phone',
                'country_code',
                'latitude',
                'longitude',
            ]);
    }

    public function test_latitude_and_longitude_must_be_provided_together(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/addresses', [
            'label' => 'Maison',
            'country_code' => 'CD',
            'city' => 'Goma',
            'address_line_1' => 'Avenue',
            'latitude' => -1.68,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }
}
