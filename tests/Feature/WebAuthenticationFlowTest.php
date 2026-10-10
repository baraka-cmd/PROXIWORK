<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_active_user_can_log_in_and_is_redirected_to_dashboard_resolver(): void
    {
        $user = User::factory()->create([
            'email' => 'CLIENT@example.com',
            'password' => Hash::make('SecurePass1!'),
            'account_status' => UserAccountStatus::ACTIVE,
        ]);
        $user->assignRole('client');

        $response = $this->from('/login')->post(route('login.store'), [
            'email' => '  CLIENT@example.com ',
            'password' => 'SecurePass1!',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_account_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('SecurePass1!'),
            'account_status' => UserAccountStatus::SUSPENDED,
        ]);
        $user->assignRole('client');

        $this->from('/login')->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecurePass1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_invalidates_authenticated_session_and_returns_to_public_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_client_registration_creates_profile_and_notification_preferences(): void
    {
        $this->post(route('register.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '+243900000000',
            'email' => 'JEAN@example.com',
            'account_type' => 'client',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'terms' => '1',
        ])->assertRedirect(route('client.dashboard'));

        $user = User::query()->where('email', 'jean@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '+243900000000',
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'database_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => false,
        ]);
    }

    public function test_professional_registration_creates_private_pending_profile(): void
    {
        $this->post(route('register.store'), [
            'first_name' => 'Marie',
            'last_name' => 'Kasereka',
            'email' => 'marie@example.com',
            'account_type' => 'professional',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'terms' => '1',
        ])->assertRedirect(route('professional.dashboard'));

        $user = User::query()->where('email', 'marie@example.com')->firstOrFail();
        $profile = $user->professionalProfile()->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('professional'));
        $this->assertSame(ProfessionalVerificationStatus::PENDING, $profile->verification_status);
        $this->assertSame('private', $profile->visibility);
        $this->assertSame('draft', $profile->status);
    }

    public function test_public_registration_cannot_assign_administrator_role(): void
    {
        $this->from('/register')->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'account_type' => 'admin',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'terms' => '1',
        ])->assertSessionHasErrors('account_type');

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertGuest();
    }
}
