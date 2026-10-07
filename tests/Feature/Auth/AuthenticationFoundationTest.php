<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_registration_creates_client_profile_preferences_and_token(): void
    {
        Notification::fake();

        $plain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jean Dupont',
            'email' => 'jean-'.fake()->unique()->numerify('####').'@example.com',
            'password' => $plain,
            'password_confirmation' => $plain,
            'device_name' => 'android-test',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'message', 'data' => ['user', 'token', 'token_type'], 'meta',
        ])->assertJsonPath('data.token_type', 'Bearer');

        $email = $response->json('data.user.email');
        $user = User::where('email', $email)->firstOrFail();

        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_login_me_logout_and_token_revocation_work(): void
    {
        $plain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $user = User::factory()->create(['password' => Hash::make($plain)]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $plain,
            'device_name' => 'android-test',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.id', $user->id);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_credentials_are_rejected_without_creating_a_token(): void
    {
        $plain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        User::factory()->create(['password' => Hash::make($plain)]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong-'.fake()->unique()->numerify('####').'@example.com',
            'password' => $plain,
            'device_name' => 'android-test',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_change_revokes_old_tokens_and_issues_one_new_token(): void
    {
        $oldPlain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $newPlain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $user = User::factory()->create(['password' => Hash::make($oldPlain)]);
        $oldToken = $user->createToken('old-device')->plainTextToken;

        $this->withToken($oldToken)->postJson('/api/v1/auth/change-password', [
            'current_password' => $oldPlain,
            'password' => $newPlain,
            'password_confirmation' => $newPlain,
            'device_name' => 'new-device',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertTrue(Hash::check($newPlain, $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_password_reset_updates_password_revokes_tokens_and_audits(): void
    {
        Notification::fake();

        $oldPlain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $newPlain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $user = User::factory()->create(['password' => Hash::make($oldPlain)]);
        $oldToken = $user->createToken('old-device')->plainTextToken;
        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => $newPlain,
            'password_confirmation' => $newPlain,
        ])->assertOk();

        $this->assertTrue(Hash::check($newPlain, $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'password_reset']);
        Notification::assertSentTo($user, AccountActivityNotification::class);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_email_verification_requires_a_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $invalidUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('attacker@example.com')],
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
        $plain = fake()->regexify('[A-Za-z0-9]{14}[!@%]');
        $email = 'throttle-'.fake()->unique()->numerify('####').'@example.com';
        User::factory()->create(['email' => $email, 'password' => Hash::make($plain)]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => $plain.'-wrong',
                'device_name' => 'test',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $plain.'-wrong',
            'device_name' => 'test',
        ])->assertStatus(429);
    }

    public function test_protected_auth_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->postJson('/api/v1/auth/change-password', [])->assertUnauthorized();
    }
}
