<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebProfessionalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/professional')
            ->assertRedirect('/login');
    }

    public function test_client_cannot_access_professional_dashboard(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get('/professional')
            ->assertForbidden();
    }

    public function test_professional_can_view_dashboard_with_real_service_data(): void
    {
        $professional = User::factory()->create([
            'name' => 'Aline Kabeya',
        ]);
        $professional->assignRole('professional');

        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professional->getKey(),
            'professional_title' => 'Développeuse Web & Mobile',
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE,
            'rating_average' => 4.80,
            'rating_count' => 5,
        ]);

        Service::factory()->create([
            'professional_profile_id' => $profile->getKey(),
        ]);

        $this->actingAs($professional)
            ->get('/professional')
            ->assertOk()
            ->assertViewIs('professional.dashboard')
            ->assertViewHas('dashboard')
            ->assertSee('Aline Kabeya')
            ->assertSee('Développeuse Web & Mobile')
            ->assertSee('Services')
            ->assertSee('4,8')
            ->assertSee('5 avis')
            ->assertSee('Publiez au moins un service pour apparaître dans le catalogue.');
    }

    public function test_suspended_professional_cannot_access_dashboard(): void
    {
        $professional = User::factory()->suspended()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create([
            'user_id' => $professional->getKey(),
        ]);

        $this->actingAs($professional)
            ->get('/professional')
            ->assertForbidden();
    }

    public function test_dashboard_does_not_expose_another_professional_data(): void
    {
        $first = User::factory()->create(['name' => 'Premier professionnel']);
        $first->assignRole('professional');
        ProfessionalProfile::factory()->create([
            'user_id' => $first->getKey(),
            'professional_title' => 'Premier métier',
        ]);

        $second = User::factory()->create(['name' => 'Second professionnel']);
        $second->assignRole('professional');
        ProfessionalProfile::factory()->create([
            'user_id' => $second->getKey(),
            'professional_title' => 'Second métier',
        ]);

        $this->actingAs($first)
            ->get('/professional')
            ->assertOk()
            ->assertSee('Premier professionnel')
            ->assertSee('Premier métier')
            ->assertDontSee('Second professionnel')
            ->assertDontSee('Second métier');
    }

    public function test_dashboard_uses_a_supported_period_and_shows_honest_empty_states(): void
    {
        $professional = User::factory()->create(['name' => 'Professionnel Débutant']);
        $professional->assignRole('professional');

        ProfessionalProfile::factory()->create([
            'user_id' => $professional->getKey(),
            'rating_average' => 0,
            'rating_count' => 0,
        ]);

        $this->actingAs($professional)
            ->get('/professional?period=7d')
            ->assertOk()
            ->assertViewHas('dashboard', function (array $dashboard): bool {
                return $dashboard['period'] === '7d'
                    && count($dashboard['charts']['activity']['labels']) === 7
                    && $dashboard['charts']['revenue']['series'] === []
                    && $dashboard['engagement']['rating_count'] === 0
                    && $dashboard['finance']['wallet_available_known'] === false;
            })
            ->assertSee('7 derniers jours')
            ->assertSee('Aucun revenu confirmé')
            ->assertSee('Aucun avis pour le moment')
            ->assertSee('Aucun portefeuille existant à afficher');
    }

    public function test_unsupported_dashboard_period_falls_back_to_thirty_days(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->getKey()]);

        $this->actingAs($professional)
            ->get('/professional?period=all-time')
            ->assertOk()
            ->assertViewHas('dashboard', function (array $dashboard): bool {
                return $dashboard['period'] === '30d'
                    && count($dashboard['charts']['activity']['labels']) === 30;
            });
    }

    public function test_dashboard_metrics_only_count_submitted_requests_for_the_authenticated_professional(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->getKey()]);
        $service = Service::factory()->create(['professional_profile_id' => $profile->getKey()]);

        ServiceRequest::factory()->count(2)->create([
            'professional_id' => $profile->getKey(),
            'service_id' => $service->getKey(),
            'status' => ServiceRequestStatus::REQUESTED->value,
            'requested_at' => now(),
        ]);

        $otherProfessional = User::factory()->create();
        $otherProfessional->assignRole('professional');
        $otherProfile = ProfessionalProfile::factory()->create(['user_id' => $otherProfessional->getKey()]);
        $otherService = Service::factory()->create(['professional_profile_id' => $otherProfile->getKey()]);

        ServiceRequest::factory()->create([
            'professional_id' => $otherProfile->getKey(),
            'service_id' => $otherService->getKey(),
            'status' => ServiceRequestStatus::REQUESTED->value,
            'requested_at' => now(),
        ]);

        $this->actingAs($professional)
            ->get('/professional?period=30d')
            ->assertOk()
            ->assertViewHas('dashboard', function (array $dashboard) use ($service): bool {
                return $dashboard['operations']['requests_to_handle'] === 2
                    && array_sum($dashboard['charts']['activity']['requests']) === 2
                    && count($dashboard['charts']['top_services']) === 1
                    && $dashboard['charts']['top_services'][0]['service_id'] === $service->getKey()
                    && $dashboard['charts']['top_services'][0]['count'] === 2;
            });
    }

    public function test_professional_dashboard_uses_private_avatar_route_when_photo_exists(): void
    {
        Storage::fake('local');

        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->getKey()]);
        $avatarPath = UploadedFile::fake()
            ->image('avatar.png')
            ->store('professional-avatars/'.$professional->getKey(), 'local');

        $professional->profile()->create([
            'first_name' => 'Aline',
            'last_name' => 'Kabeya',
            'avatar_path' => $avatarPath,
        ]);

        $this->actingAs($professional)
            ->get('/professional')
            ->assertOk()
            ->assertSee(route('professional.profile.photo'), false);

        $this->get(route('professional.profile.photo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_dashboard_module_links_accept_the_supported_order_and_quote_filters(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->getKey()]);

        $this->actingAs($professional)
            ->get('/professional/orders?status=active')
            ->assertOk()
            ->assertViewHas('selectedStatus', 'active');

        $this->get('/professional/orders?status=completed&from=2026-10-01&to=2026-10-13')
            ->assertOk()
            ->assertViewHas('selectedStatus', 'completed')
            ->assertViewHas('from', '2026-10-01')
            ->assertViewHas('to', '2026-10-13');

        $this->get('/professional/quotes?action_required=1')
            ->assertOk()
            ->assertViewHas('actionRequired', true);
    }

    public function test_order_date_filter_rejects_a_reversed_date_range(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->getKey()]);

        $this->actingAs($professional)
            ->from('/professional/orders')
            ->get('/professional/orders?from=2026-10-15&to=2026-10-01')
            ->assertRedirect('/professional/orders')
            ->assertSessionHasErrors('to');
    }


}
