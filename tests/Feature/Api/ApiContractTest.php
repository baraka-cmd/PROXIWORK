<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_authenticated_success_response_uses_standard_envelope(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->profile()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
                'meta',
            ])
            ->assertJsonMissingPath('success');
    }

    public function test_validation_error_uses_standard_error_envelope(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ])
            ->assertJsonMissingPath('data');
    }

    public function test_unauthenticated_api_request_returns_401_contract(): void
    {
        $this->getJson('/api/v1/profile')
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('errors', []);
    }

    public function test_forbidden_api_request_returns_403_contract(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/rbac/roles')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_not_found_api_request_returns_404_contract(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/addresses/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_rate_limit_returns_429(): void
    {
        User::factory()->create([
            'email' => 'rate@example.com',
            'password' => Hash::make('SecurePass1!'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rate@example.com',
                'password' => 'WrongPass1!',
                'device_name' => 'test-device',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rate@example.com',
            'password' => 'WrongPass1!',
            'device_name' => 'test-device',
        ])
            ->assertTooManyRequests()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->profile()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
