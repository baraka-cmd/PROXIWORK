<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_login_from_client_services(): void
    {
        $this->get('/client/services')->assertRedirect('/login');
    }

    public function test_non_client_cannot_open_client_services(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)->get('/client/services')->assertForbidden();
    }

    public function test_client_can_open_catalogue_and_see_real_overview_counts(): void
    {
        $client = $this->client();
        $category = Category::factory()->create(['status' => CategoryStatus::ACTIVE]);
        $service = $this->publishedService([
            'category_id' => $category->id,
            'title' => 'Plomberie résidentielle',
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '75.00',
            'currency' => 'USD',
        ]);

        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'professional_id' => $service->professional_profile_id,
            'status' => ServiceRequestStatus::DRAFT,
        ]);

        $response = $this->actingAs($client)
            ->get('/client/services')
            ->assertOk()
            ->assertViewIs('client.services.index')
            ->assertSee('Mes services')
            ->assertSee('Plomberie résidentielle')
            ->assertSee('Prix fixe')
            ->assertSee('SRV-'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Suivi de mes demandes')
            ->assertSee('Brouillons');

        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['services']);
        $this->assertGreaterThanOrEqual(1, $stats['categories']);
        $this->assertSame(1, $stats['professionals']);
        $this->assertSame(1, $stats['requests']);
    }

    public function test_catalogue_hides_unpublished_services_and_unavailable_professionals(): void
    {
        $client = $this->client();

        $this->publishedService(['title' => 'Service visible']);
        $this->publishedService([
            'title' => 'Service non publié',
            'status' => ServiceStatus::DRAFT,
            'published_at' => null,
        ]);

        $this->publishedService(['title' => 'Service privé'], [
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
        ]);

        $this->publishedService(['title' => 'Service non vérifié'], [
            'verification_status' => ProfessionalVerificationStatus::PENDING,
        ]);

        $this->publishedService(['title' => 'Professionnel suspendu'], [], [
            'account_status' => UserAccountStatus::SUSPENDED,
        ]);

        $inactiveCategory = Category::factory()->create(['status' => CategoryStatus::INACTIVE]);
        $this->publishedService([
            'title' => 'Service catégorie inactive',
            'category_id' => $inactiveCategory->id,
        ]);

        $this->actingAs($client)
            ->get('/client/services')
            ->assertOk()
            ->assertSee('Service visible')
            ->assertDontSee('Service non publié')
            ->assertDontSee('Service privé')
            ->assertDontSee('Service non vérifié')
            ->assertDontSee('Professionnel suspendu')
            ->assertDontSee('Service catégorie inactive');
    }

    public function test_catalogue_filters_by_search_category_pricing_and_city(): void
    {
        $client = $this->client();
        $category = Category::factory()->create(['name' => 'Maison']);
        $service = $this->publishedService([
            'category_id' => $category->id,
            'title' => 'Réparation plomberie',
            'pricing_type' => ServicePricingType::FIXED,
        ], ['city' => 'Goma']);

        $this->publishedService([
            'title' => 'Création logo',
            'pricing_type' => ServicePricingType::QUOTE,
        ], ['city' => 'Bukavu']);

        $this->actingAs($client)
            ->get('/client/services?search=plomberie&category='.$category->id.'&pricing=fixed&city=Goma')
            ->assertOk()
            ->assertSee('Réparation plomberie')
            ->assertDontSee('Création logo')
            ->assertSee('name="pricing"', false)
            ->assertSee('name="city"', false);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'title' => 'Réparation plomberie']);
    }

    public function test_invalid_pricing_filter_is_rejected_by_server_validation(): void
    {
        $this->actingAs($this->client())
            ->get('/client/services?pricing=unrecognized')
            ->assertRedirect()
            ->assertSessionHasErrors('pricing');
    }

    public function test_request_status_shortcuts_use_real_client_owned_counts(): void
    {
        $client = $this->client();
        $otherClient = $this->client();

        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceRequestStatus::DRAFT,
        ]);
        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceRequestStatus::REQUESTED,
        ]);
        ServiceRequest::factory()->create([
            'client_id' => $otherClient->id,
            'status' => ServiceRequestStatus::DRAFT,
        ]);

        $response = $this->actingAs($client)->get('/client/services')->assertOk();
        $statuses = collect($response->viewData('requestStatuses'))->keyBy('value');

        $this->assertSame(2, $statuses->get(null)['count']);
        $this->assertSame(1, $statuses->get(ServiceRequestStatus::DRAFT->value)['count']);
        $this->assertSame(1, $statuses->get(ServiceRequestStatus::REQUESTED->value)['count']);
    }

    private function client(): User
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        return $client;
    }

    private function publishedService(
        array $serviceAttributes = [],
        array $profileAttributes = [],
        array $professionalUserAttributes = [],
    ): Service {
        $professionalUser = User::factory()->create($professionalUserAttributes);
        $professionalUser->assignRole('professional');

        $profile = ProfessionalProfile::factory()->create(array_merge([
            'user_id' => $professionalUser->id,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'verified_at' => now(),
        ], $profileAttributes));

        $category = Category::factory()->create();

        return Service::factory()->create(array_merge([
            'professional_profile_id' => $profile->id,
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ], $serviceAttributes));
    }
}
