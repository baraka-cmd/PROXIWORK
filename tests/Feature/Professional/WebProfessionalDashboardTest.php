<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Créez votre premier service');
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
}
