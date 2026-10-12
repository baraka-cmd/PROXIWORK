<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Ravi de vous revoir')
            ->assertSee('Continuer avec Google');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => Hash::make('Password123!'),
            'account_status' => UserAccountStatus::ACTIVE,
        ]);

        $user->assignRole('client');

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_dashboard_redirects_client_to_client_workspace(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_dashboard_redirects_professional_to_professional_workspace(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('professional.dashboard'));
    }

    public function test_dashboard_rejects_accounts_without_a_workspace_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'client@example.com',
            'password' => Hash::make('Password123!'),
            'account_status' => UserAccountStatus::ACTIVE,
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'client@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('Password123!'),
            'account_status' => UserAccountStatus::SUSPENDED,
        ]);

        $this->post(route('login.store'), [
            'email' => 'suspended@example.com',
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout_from_the_shared_web_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_authenticated_user_can_request_web_email_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_web_email_verification_requires_the_authenticated_user_and_signed_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify.web',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($url)->assertRedirect('/login');

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
