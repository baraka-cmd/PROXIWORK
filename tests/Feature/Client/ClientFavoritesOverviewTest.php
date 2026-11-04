<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFavoritesOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_save_and_remove_a_published_service(): void
    {
        $client = $this->client();
        $service = $this->publishedService();

        $this->actingAs($client)
            ->post('/client/favorites/services/'.$service->id)
            ->assertRedirect();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $client->id,
            'professional_profile_id' => null,
            'service_id' => $service->id,
        ]);

        $favorite = Favorite::query()
            ->where('user_id', $client->id)
            ->where('service_id', $service->id)
            ->firstOrFail();

        $this->actingAs($client)
            ->delete('/client/favorites/'.$favorite->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_guests_and_non_clients_cannot_manage_client_favorites(): void
    {
        $service = $this->publishedService();

        $this->post('/client/favorites/services/'.$service->id)->assertRedirect('/login');

        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)
            ->post('/client/favorites/services/'.$service->id)
            ->assertForbidden();

        $this->actingAs($professional)
            ->get('/client/favorites')
            ->assertForbidden();
    }

    public function test_service_favorites_are_scoped_to_the_authenticated_client(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $service = $this->publishedService();

        $favorite = Favorite::create([
            'user_id' => $otherClient->id,
            'service_id' => $service->id,
            'professional_profile_id' => null,
        ]);

        $this->actingAs($client)
            ->delete('/client/favorites/'.$favorite->id)
            ->assertForbidden();

        $this->assertDatabaseHas('favorites', ['id' => $favorite->id, 'user_id' => $otherClient->id]);
    }

    public function test_unpublished_service_cannot_be_added_to_favorites(): void
    {
        $client = $this->client();
        $service = $this->publishedService([
            'status' => ServiceStatus::DRAFT,
            'published_at' => null,
        ]);

        $this->actingAs($client)
            ->post('/client/favorites/services/'.$service->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $client->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_overview_counts_search_and_type_filters_use_only_the_current_client(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $service = $this->publishedService(['title' => 'Réparation plomberie']);

        Favorite::create([
            'user_id' => $client->id,
            'service_id' => $service->id,
            'professional_profile_id' => null,
        ]);

        $professional = $this->visibleProfessional();
        Favorite::create([
            'user_id' => $client->id,
            'professional_profile_id' => $professional->id,
            'service_id' => null,
        ]);

        Favorite::create([
            'user_id' => $otherClient->id,
            'service_id' => $service->id,
            'professional_profile_id' => null,
        ]);

        $response = $this->actingAs($client)
            ->get('/client/favorites?type=services&search=plomberie&sort=name')
            ->assertOk()
            ->assertViewIs('client.favorites.index')
            ->assertSee('Réparation plomberie')
            ->assertDontSee($professional->user->name);

        $this->assertSame(2, $response->viewData('stats')['all']);
        $this->assertSame(1, $response->viewData('stats')['professionals']);
        $this->assertSame(1, $response->viewData('stats')['services']);
        $this->assertSame(1, $response->viewData('favorites')->total());
    }

    public function test_invalid_filter_is_rejected_without_repeating_the_invalid_query(): void
    {
        $this->actingAs($this->client())
            ->get('/client/favorites?type=unexpected')
            ->assertRedirect(route('client.favorites.index'))
            ->assertSessionHasErrors('type');
    }

    public function test_favorites_page_shows_the_verified_public_business_name(): void
    {
        $client = $this->client();
        $professional = $this->visibleProfessional();
        $professional->forceFill(['business_name' => 'Atelier public'])->save();
        Favorite::create([
            'user_id' => $client->id,
            'professional_profile_id' => $professional->id,
            'service_id' => null,
        ]);

        $this->actingAs($client)
            ->get('/client/favorites')
            ->assertOk()
            ->assertSee('Atelier public')
            ->assertSee('Vérifié');
    }

    public function test_favorites_page_hides_details_and_verification_badge_for_a_private_professional(): void
    {
        $client = $this->client();
        $professional = $this->visibleProfessional();
        $professional->forceFill([
            'business_name' => 'Entreprise privée',
            'professional_title' => 'Expertise privée',
            'city' => 'Ville privée',
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
            'verification_status' => ProfessionalVerificationStatus::PENDING,
            'verified_at' => null,
        ])->save();
        Favorite::create([
            'user_id' => $client->id,
            'professional_profile_id' => $professional->id,
            'service_id' => null,
        ]);

        $this->actingAs($client)
            ->get('/client/favorites')
            ->assertOk()
            ->assertSee('Professionnel devenu indisponible')
            ->assertDontSee('Entreprise privée')
            ->assertDontSee('Expertise privée')
            ->assertDontSee('Ville privée')
            ->assertDontSee('Vérifié');
    }

    public function test_service_search_shows_the_remove_action_after_a_service_is_favorited(): void
    {
        $client = $this->client();
        $service = $this->publishedService();

        $this->actingAs($client)
            ->post('/client/favorites/services/'.$service->id)
            ->assertRedirect();

        $favorite = Favorite::query()
            ->where('user_id', $client->id)
            ->where('service_id', $service->id)
            ->firstOrFail();

        $this->actingAs($client)
            ->get('/client/recherche')
            ->assertOk()
            ->assertSee(route('client.favorites.destroy', $favorite), false)
            ->assertSee('Retirer des favoris');

        $this->actingAs($client)
            ->get(route('public.services.show', $service))
            ->assertOk()
            ->assertSee(route('client.favorites.destroy', $favorite), false)
            ->assertSee('Retirer des favoris');
    }

    private function client(): User
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        return $client;
    }

    private function visibleProfessional(): ProfessionalProfile
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        return ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'verified_at' => now(),
        ]);
    }

    private function publishedService(array $attributes = []): Service
    {
        $professional = $this->visibleProfessional();
        $category = Category::factory()->create(['status' => CategoryStatus::ACTIVE]);

        return Service::factory()->create(array_merge([
            'professional_profile_id' => $professional->id,
            'category_id' => $category->id,
            'title' => 'Service professionnel',
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ], $attributes));
    }
}
