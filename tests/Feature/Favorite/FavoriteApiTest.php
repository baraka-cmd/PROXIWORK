<?php

declare(strict_types=1);

namespace Tests\Feature\Favorite;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_authenticated_user_can_add_a_professional_to_favorites(): void
    {
        $user = $this->user();
        $professional = $this->professional();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/favorites/'.$professional->id)
            ->assertCreated()
            ->assertJsonPath('data.professional_profile_id', $professional->id)
            ->assertJsonPath('meta.created', true);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'professional_profile_id' => $professional->id,
        ]);

        $this->assertArrayNotHasKey('email', $response->json('data.professional'));
    }

    public function test_adding_the_same_favorite_is_idempotent(): void
    {
        $user = $this->user();
        $professional = $this->professional();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/favorites/'.$professional->id)
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/favorites/'.$professional->id)
            ->assertOk()
            ->assertJsonPath('meta.created', false);

        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_user_can_list_only_their_own_favorites(): void
    {
        $user = $this->user();
        $otherUser = $this->user();
        $professionalA = $this->professional();
        $professionalB = $this->professional();

        Favorite::create([
            'user_id' => $user->id,
            'professional_profile_id' => $professionalA->id,
        ]);
        Favorite::create([
            'user_id' => $otherUser->id,
            'professional_profile_id' => $professionalB->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.professional_profile_id', $professionalA->id);
    }

    public function test_user_can_remove_own_favorite(): void
    {
        $user = $this->user();
        $professional = $this->professional();

        Favorite::create([
            'user_id' => $user->id,
            'professional_profile_id' => $professional->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/favorites/'.$professional->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'professional_profile_id' => $professional->id,
        ]);
    }

    public function test_removing_a_missing_favorite_is_idempotent(): void
    {
        $user = $this->user();
        $professional = $this->professional();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/favorites/'.$professional->id)
            ->assertNoContent();
    }

    public function test_user_cannot_favorite_their_own_professional_profile(): void
    {
        $user = $this->user();
        $professional = ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/favorites/'.$professional->id)
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_favorite(): void
    {
        $user = $this->user();
        $owner = $this->user();
        $professional = $this->professional();

        Favorite::create([
            'user_id' => $owner->id,
            'professional_profile_id' => $professional->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/favorites/'.$professional->id)
            ->assertNoContent();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $owner->id,
            'professional_profile_id' => $professional->id,
        ]);
    }

    public function test_favorite_routes_require_authentication(): void
    {
        $professional = $this->professional();

        $this->getJson('/api/v1/favorites')->assertUnauthorized();
        $this->putJson('/api/v1/favorites/'.$professional->id)->assertUnauthorized();
        $this->deleteJson('/api/v1/favorites/'.$professional->id)->assertUnauthorized();
    }

    public function test_missing_professional_returns_not_found(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/favorites/999999')
            ->assertNotFound();
    }

    public function test_database_prevents_duplicate_favorites(): void
    {
        $user = $this->user();
        $professional = $this->professional();

        Favorite::create([
            'user_id' => $user->id,
            'professional_profile_id' => $professional->id,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Favorite::create([
            'user_id' => $user->id,
            'professional_profile_id' => $professional->id,
        ]);
    }

    public function test_favorites_pagination_is_bounded(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/favorites?per_page=101')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    private function professional(): ProfessionalProfile
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        return ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);
    }
}
