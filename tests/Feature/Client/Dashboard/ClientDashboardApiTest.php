<?php

declare(strict_types=1);

namespace Tests\Feature\Client\Dashboard;

use App\Models\Address;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_view_their_dashboard(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('meta.scope', 'client')
            ->assertJsonPath('data.profile.user.id', $client->id);
    }

    public function test_guest_cannot_access_client_dashboard(): void
    {
        $this->getJson('/api/v1/client/dashboard')
            ->assertUnauthorized();
    }

    public function test_professional_cannot_access_client_dashboard(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_isolated_to_authenticated_client(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        Address::factory()->create([
            'user_id' => $client->id,
            'label' => 'Chez moi',
            'is_default' => true,
        ]);
        Address::factory()->count(2)->create(['user_id' => $otherClient->id]);

        Favorite::create([
            'user_id' => $client->id,
            'professional_profile_id' => $professional->id,
        ]);
        Favorite::create([
            'user_id' => $otherClient->id,
            'professional_profile_id' => $professional->id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('data.addresses.total', 1)
            ->assertJsonPath('data.addresses.default.label', 'Chez moi')
            ->assertJsonPath('data.favorites.count', 1);
    }

    public function test_dashboard_reports_unread_notifications_only_for_current_client(): void
    {
        $client = $this->client();
        $otherClient = $this->client();

        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $client->id,
            'data' => json_encode(['title' => 'Unread']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $client->id,
            'data' => json_encode(['title' => 'Read']),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherClient->id,
            'data' => json_encode(['title' => 'Other']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('data.notifications.unread', 1);
    }

    public function test_dashboard_handles_client_without_profile_or_addresses(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('data.profile.details', null)
            ->assertJsonPath('data.addresses.total', 0)
            ->assertJsonPath('data.addresses.default', null)
            ->assertJsonPath('data.favorites.count', 0)
            ->assertJsonPath('data.notifications.unread', 0)
            ->assertJsonPath('data.pending_actions.0.type', 'profile')
            ->assertJsonPath('data.pending_actions.1.type', 'addresses');
    }

    public function test_dashboard_does_not_expose_sensitive_user_fields(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('data.profile.user.password')
            ->assertJsonMissingPath('data.profile.user.remember_token')
            ->assertJsonMissingPath('data.profile.user.roles')
            ->assertJsonMissingPath('data.profile.user.tokens');
    }

    public function test_dashboard_avoids_n_plus_one_query_growth(): void
    {
        $client = $this->client();
        Address::factory()->count(5)->create(['user_id' => $client->id]);

        DB::enableQueryLog();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, $queryCount);
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
