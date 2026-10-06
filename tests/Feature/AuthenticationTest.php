<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_user_can_register(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'device_name' => 'android-test',
        ]);

        $response->assertCreated()
                        ->assertJsonPath('data.user.email', 'jean@example.com')
            ->assertJsonStructure(['success', 'message', 'data' => ['user', 'token', 'token_type']]);

        $this->assertDatabaseHas('users', ['email' => 'jean@example.com']);
        $user = User::where('email', 'jean@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jean@example.com',
            'password' => Hash::make('SecurePass1!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecurePass1!',
            'device_name' => 'android-test',
        ])->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'jean@example.com',
            'password' => Hash::make('SecurePass1!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jean@example.com',
            'password' => 'WrongPass1!',
            'device_name' => 'android-test',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_read_profile(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test-device')->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_authenticated_user_can_logout_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_can_be_changed_and_old_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SecurePass1!')]);
        $oldToken = $user->createToken('old-device')->plainTextToken;

        $this->withToken($oldToken)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'SecurePass1!',
            'password' => 'NewSecurePass2!',
            'password_confirmation' => 'NewSecurePass2!',
            'device_name' => 'new-device',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertTrue(Hash::check('NewSecurePass2!', $user->fresh()->password));
    }
}
