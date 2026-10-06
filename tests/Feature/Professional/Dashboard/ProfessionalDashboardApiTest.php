<?php

declare(strict_types=1);

namespace Tests\Feature\Professional\Dashboard;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfessionalDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_view_only_their_dashboard(): void
    {
        [$professional, $profile] = $this->professional();
        $otherProfile = ProfessionalProfile::factory()->create();

        Service::factory()->count(2)->create(['professional_profile_id' => $profile->id]);
        Service::factory()->create(['professional_profile_id' => $otherProfile->id]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertOk()
            ->assertJsonPath('meta.scope', 'professional')
            ->assertJsonPath('data.profile.id', $profile->id)
            ->assertJsonPath('data.services.total', 2);
    }

    public function test_client_cannot_access_professional_dashboard(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_professional_dashboard(): void
    {
        $this->getJson('/api/v1/professional/dashboard')
            ->assertUnauthorized();
    }

    public function test_dashboard_counts_service_statuses_correctly(): void
    {
        [$professional, $profile] = $this->professional();

        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'status' => ServiceStatus::DRAFT,
        ]);
        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'status' => ServiceStatus::UNPUBLISHED,
        ]);
        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'status' => ServiceStatus::ARCHIVED,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertOk()
            ->assertJsonPath('data.services.total', 4)
            ->assertJsonPath('data.services.published', 1)
            ->assertJsonPath('data.services.draft', 1)
            ->assertJsonPath('data.services.unpublished', 1)
            ->assertJsonPath('data.services.archived', 1);
    }

    public function test_unpublished_service_is_not_counted_as_published_without_publication_date(): void
    {
        [$professional, $profile] = $this->professional();

        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => null,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertOk()
            ->assertJsonPath('data.services.total', 1)
            ->assertJsonPath('data.services.published', 0);
    }

    public function test_dashboard_exposes_professional_profile_without_sensitive_fields(): void
    {
        [$professional, $profile] = $this->professional();
        $profile->update([
            'professional_title' => 'Développeur Laravel',
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE,
            'rating_average' => 4.75,
            'rating_count' => 12,
        ]);
        $professional->profile()->create([
            'first_name' => 'Baraka',
            'last_name' => 'Ntwali',
            'phone' => '+243000000000',
            'bio' => 'Développeur.',
            'locale' => 'fr',
            'timezone' => 'Africa/Kinshasa',
        ]);

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertOk();

        $response
            ->assertJsonPath('data.profile.professional_title', 'Développeur Laravel')
            ->assertJsonPath('data.profile.verification_status', 'verified')
            ->assertJsonPath('data.profile.availability_status', 'available')
            ->assertJsonPath('data.profile.rating_average', '4.75')
            ->assertJsonMissingPath('data.profile.user.password')
            ->assertJsonMissingPath('data.profile.user.remember_token')
            ->assertJsonMissingPath('data.profile.user.roles')
            ->assertJsonMissingPath('data.profile.user.tokens');
    }

    public function test_dashboard_reports_unread_notifications(): void
    {
        [$professional] = $this->professional();

        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $professional->id,
            'data' => json_encode(['title' => 'Test', 'message' => 'Test']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertOk()
            ->assertJsonPath('data.notifications.unread', 1);
    }

    public function test_new_professional_receives_action_to_create_service(): void
    {
        [$professional] = $this->professional();

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_actions.0.type', 'services');
    }

    private function professional(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        return [$user, $profile];
    }
}
