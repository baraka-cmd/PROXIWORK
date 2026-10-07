<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebClientFavoritesRequestsQuotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_open_favorites_requests_and_quotes_pages(): void
    {
        $client = $this->client();

        $this->actingAs($client)->get('/client/favorites')->assertOk()->assertViewIs('client.favorites.index');
        $this->actingAs($client)->get('/client/requests')->assertOk()->assertViewIs('client.requests.index');
        $this->actingAs($client)->get('/client/quotes')->assertOk()->assertViewIs('client.quotes.index');
    }

    public function test_guest_is_redirected_to_login_for_all_three_areas(): void
    {
        $this->get('/client/favorites')->assertRedirect('/login');
        $this->get('/client/requests')->assertRedirect('/login');
        $this->get('/client/quotes')->assertRedirect('/login');
    }

    public function test_non_client_cannot_open_client_modules(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)->get('/client/favorites')->assertForbidden();
        $this->actingAs($professional)->get('/client/requests')->assertForbidden();
        $this->actingAs($professional)->get('/client/quotes')->assertForbidden();
    }

    public function test_favorites_page_only_exposes_authenticated_clients_favorites(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);

        Favorite::create(['user_id' => $client->id, 'professional_profile_id' => $professional->id]);

        $response = $this->actingAs($client)->get('/client/favorites')->assertOk();
        $response->assertSee($professionalUser->name);

        Favorite::create(['user_id' => $otherClient->id, 'professional_profile_id' => $professional->id]);

        $this->actingAs($client)->get('/client/favorites')->assertOk();
    }

    public function test_client_can_add_and_remove_a_favorite_through_web(): void
    {
        $client = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);

        $this->actingAs($client)
            ->put('/client/favorites/'.$professional->id)
            ->assertRedirect();

        $favorite = Favorite::query()->where('user_id', $client->id)->firstOrFail();

        $this->actingAs($client)
            ->delete('/client/favorites/'.$favorite->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_client_request_detail_is_scoped_by_policy(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $serviceRequest = ServiceRequest::factory()->create(['client_id' => $owner->id]);

        $this->actingAs($owner)->get('/client/requests/'.$serviceRequest->id)->assertOk();
        $this->actingAs($other)->get('/client/requests/'.$serviceRequest->id)->assertForbidden();
    }

    public function test_quote_detail_is_scoped_by_policy(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $serviceRequest = ServiceRequest::factory()->create(['client_id' => $owner->id]);
        $quotation = Quotation::query()->forceCreate(['service_request_id' => $serviceRequest->id, 'status' => 'sent']);

        $this->actingAs($owner)->get('/client/quotes/'.$quotation->id)->assertOk();
        $this->actingAs($other)->get('/client/quotes/'.$quotation->id)->assertForbidden();
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
