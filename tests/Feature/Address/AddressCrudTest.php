<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_authenticated_user_can_list_only_their_addresses(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Address::factory()->for($user)->create(['label' => 'Maison']);
        Address::factory()->for($otherUser)->create(['label' => 'Autre']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/addresses');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Maison');
    }

    public function test_user_can_create_an_address_without_setting_default(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/addresses', [
            'label' => 'Maison',
            'country_code' => 'cd',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'address_line_1' => 'Avenue du Lac',
            'latitude' => -1.68,
            'longitude' => 29.23,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.country_code', 'CD')
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'country_code' => 'CD',
            'is_default' => false,
        ]);
    }

    public function test_user_can_show_update_and_delete_their_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/addresses/{$address->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $address->id);

        $this->patchJson("/api/v1/addresses/{$address->id}", [
            'label' => 'Bureau',
            'city' => 'Bukavu',
        ])->assertOk()
            ->assertJsonPath('data.label', 'Bureau')
            ->assertJsonPath('data.city', 'Bukavu');

        $this->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertOk();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_is_default_is_not_mass_assignable_through_crud(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/addresses', [
            'label' => 'Maison',
            'country_code' => 'CD',
            'city' => 'Goma',
            'address_line_1' => 'Avenue du Lac',
            'is_default' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_default', false);
    }

    public function test_validation_rejects_invalid_coordinates_and_missing_pair(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/addresses', [
            'label' => 'Maison',
            'country_code' => 'CD',
            'city' => 'Goma',
            'address_line_1' => 'Avenue du Lac',
            'latitude' => 120,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_user_cannot_access_another_users_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/addresses/{$address->id}")
            ->assertForbidden();

        $this->patchJson("/api/v1/addresses/{$address->id}", ['label' => 'Intrusion'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'label' => $address->label,
        ]);
    }

    public function test_missing_address_returns_not_found(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/addresses/999999')
            ->assertNotFound();
    }

    public function test_per_page_is_bounded(): void
    {
        $user = User::factory()->create();
        Address::factory()->count(3)->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/addresses?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }
}
