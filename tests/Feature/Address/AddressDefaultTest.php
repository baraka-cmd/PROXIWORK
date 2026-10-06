<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_first_created_address_becomes_default(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/addresses', [
            'label' => 'Maison',
            'country_code' => 'CD',
            'city' => 'Goma',
            'address_line_1' => 'Avenue du Lac',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', [
            'id' => $response->json('data.id'),
            'is_default' => true,
        ]);
    }

    public function test_setting_default_demotes_the_previous_default(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        $second = Address::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', [
            'id' => $first->id,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('addresses', [
            'id' => $second->id,
            'is_default' => true,
        ]);
    }

    public function test_setting_another_default_keeps_exactly_one_default(): void
    {
        $user = User::factory()->create();
        $addresses = Address::factory()->count(4)->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/addresses/{$addresses[2]->id}/default")
            ->assertOk();

        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_deleting_default_promotes_the_most_recent_remaining_address(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        $replacement = Address::factory()->for($user)->create();
        $latest = Address::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/addresses/{$first->id}")
            ->assertOk();

        $this->assertDatabaseHas('addresses', [
            'id' => $latest->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('addresses', [
            'id' => $replacement->id,
            'is_default' => false,
        ]);
    }

    public function test_deleting_only_address_leaves_no_default(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertOk();

        $this->assertSame(0, $user->addresses()->where('is_default', true)->count());
    }

    public function test_cannot_set_another_users_address_as_default(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/addresses/{$address->id}/default")
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'is_default' => false,
        ]);
    }
}
