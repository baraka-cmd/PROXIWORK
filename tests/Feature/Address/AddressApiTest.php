<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Models\Address;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function payload(string $label = 'Maison'): array
    {
        return [
            'label' => $label,
            'recipient_name' => 'Jean Dupont',
            'contact_phone' => '+243 900 000 000',
            'country_code' => 'cd',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'commune' => 'Goma',
            'neighborhood' => 'Katindo',
            'address_line_1' => 'Avenue de la Paix 10',
            'landmark' => 'Pres du marche',
            'latitude' => -1.6792,
            'longitude' => 29.2285,
        ];
    }

    public function test_guest_cannot_manage_addresses(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/addresses', $this->payload())->assertUnauthorized();
    }

    public function test_first_address_becomes_default_and_second_does_not(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/addresses', $this->payload())
            ->assertCreated()->assertJsonPath('data.is_default', true);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/addresses', $this->payload('Bureau'))
            ->assertCreated()->assertJsonPath('data.is_default', false);

        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_user_can_list_show_update_and_set_default_its_addresses(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        $second = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/addresses?per_page=1')
            ->assertOk()->assertJsonPath('meta.per_page', 1);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/addresses/'.$first->id)
            ->assertOk()->assertJsonPath('data.id', $first->id);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/addresses/'.$first->id, ['city' => 'Bukavu'])
            ->assertOk()->assertJsonPath('data.city', 'Bukavu');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/addresses/'.$second->id.'/default')
            ->assertOk()->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_address_ownership_prevents_idor(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('client');
        $attacker = User::factory()->create();
        $attacker->assignRole('client');
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($attacker, 'sanctum')->getJson('/api/v1/addresses/'.$address->id)->assertForbidden();
        $this->actingAs($attacker, 'sanctum')->patchJson('/api/v1/addresses/'.$address->id, ['city' => 'Bukavu'])->assertForbidden();
        $this->actingAs($attacker, 'sanctum')->postJson('/api/v1/addresses/'.$address->id.'/default')->assertForbidden();
        $this->actingAs($attacker, 'sanctum')->deleteJson('/api/v1/addresses/'.$address->id)->assertForbidden();
    }

    public function test_address_validation_rejects_invalid_coordinates_and_phone(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/addresses', [
            ...$this->payload(),
            'contact_phone' => 'abc',
            'latitude' => 100,
            'longitude' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'contact_phone', 'latitude', 'longitude',
        ]);
    }

    public function test_deleting_default_address_promotes_the_latest_remaining_address(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->assignRole('client');
        $old = Address::factory()->for($user)->create(['is_default' => false]);
        $default = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/addresses/'.$default->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('addresses', ['id' => $default->id]);
        $this->assertTrue($old->fresh()->is_default);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id, 'action' => 'address_deleted',
        ]);
        Notification::assertSentTo($user, AccountActivityNotification::class);
    }

    public function test_deleting_the_only_address_leaves_no_default(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/addresses/'.$address->id)
            ->assertNoContent();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_address_pagination_is_bounded(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        Address::factory()->count(3)->for($user)->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/addresses?per_page=999')
            ->assertOk()->assertJsonPath('meta.per_page', 100);
    }
}
