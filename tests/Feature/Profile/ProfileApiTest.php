<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_read_own_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->profile()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_client_can_update_own_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->profile()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/profile', [
                'first_name' => 'Baraka',
                'last_name' => 'Ntwali',
                'bio' => 'Développeur.',
                'locale' => 'fr',
                'timezone' => 'Africa/Kinshasa',
            ])
            ->assertOk()
                        ->assertJsonPath('data.first_name', 'Baraka')
            ->assertJsonPath('data.timezone', 'Africa/Kinshasa');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Baraka',
            'last_name' => 'Ntwali',
        ]);
    }

    public function test_missing_profile_is_not_created_as_a_side_effect(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertNotFound();

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    }

    public function test_user_cannot_update_another_users_profile_through_policy(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('client');
        $profile = $owner->profile()->create();

        $otherUser = User::factory()->create();
        $otherUser->assignRole('client');

        $this->assertFalse($otherUser->can('view', $profile));
        $this->assertFalse($otherUser->can('update', $profile));
    }

    public function test_profile_update_rejects_invalid_timezone_and_locale(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/profile', [
                'locale' => 'INVALID',
                'timezone' => 'Not/A/Timezone',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale', 'timezone']);
    }

    public function test_profile_resource_exposes_only_profile_fields(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->profile()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk();

        $response->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'first_name',
                    'last_name',
                    'phone',
                    'bio',
                    'avatar_url',
                    'locale',
                    'timezone',
                ],
            ]);
    }

    public function test_user_without_profile_permission_cannot_create_profile_as_a_side_effect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertForbidden();

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    }
}
