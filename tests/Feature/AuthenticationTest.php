<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
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
            ->assertJsonStructure(['message', 'data' => ['user', 'token', 'token_type'], 'meta'])
            ->assertJsonMissingPath('success');

        $this->assertDatabaseHas('users', ['email' => 'jean@example.com']);
        $user = User::where('email', 'jean@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
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
        ])->assertUnauthorized()->assertJsonStructure(['message', 'errors']);
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

    public function test_password_reset_updates_password_revokes_tokens_and_audits(): void
    {
        Notification::fake();

        $oldPassword = 'OldSecurePass1!';
        $newPassword = 'NewSecurePass2!';
        $user = User::factory()->create(['password' => Hash::make($oldPassword)]);
        $user->createToken('old-device');
        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertOk();

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'password_reset']);
        Notification::assertSentTo($user, AccountActivityNotification::class);
    }

    public function test_password_reset_rejects_an_invalid_token_without_changing_credentials(): void
    {
        $oldPassword = 'OldSecurePass1!';
        $user = User::factory()->create(['password' => Hash::make($oldPassword)]);
        $user->createToken('existing-device');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-reset-token',
            'password' => 'NewSecurePass2!',
            'password_confirmation' => 'NewSecurePass2!',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_password_reset_rejects_a_weak_password_without_consuming_the_token(): void
    {
        $oldPassword = 'OldSecurePass1!';
        $user = User::factory()->create(['password' => Hash::make($oldPassword)]);
        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));

        // The same token must remain usable because validation failed before the broker ran.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'NewSecurePass2!',
            'password_confirmation' => 'NewSecurePass2!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecurePass2!', $user->fresh()->password));
    }

    public function test_forgot_password_sends_a_reset_notification_to_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_email_verification_requires_a_valid_signed_url_and_matching_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $validUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($validUrl)->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $invalidUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('mismatch@example.com')],
        );

        $this->getJson($invalidUrl)->assertForbidden();
    }

    public function test_unverified_user_can_resend_verification_but_verified_user_is_not_notified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);

        Notification::fake();
        $user->markEmailAsVerified();

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $password = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $email = 'throttle-'.fake()->unique()->numerify('####').'@example.com';
        User::factory()->create(['email' => $email, 'password' => Hash::make($password)]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password.'-wrong',
                'device_name' => 'test',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password.'-wrong',
            'device_name' => 'test',
        ])->assertStatus(429);
    }

    public function test_protected_auth_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->postJson('/api/v1/auth/change-password', [])->assertUnauthorized();
        $this->postJson('/api/v1/auth/email/verification-notification')->assertUnauthorized();
    }
}
