<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('La connexion Google n’est pas encore configurée');
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

    public function test_user_with_client_and_professional_roles_can_choose_workspace(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client', 'professional');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('auth.choose-workspace')
            ->assertSee('Ouvrir mon espace client')
            ->assertSee('Ouvrir mon espace professionnel');

        $this->post(route('workspace.switch'), ['workspace' => 'professional'])
            ->assertRedirect(route('professional.dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('professional.dashboard'));
    }

    public function test_user_cannot_switch_to_a_workspace_without_the_required_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->post(route('workspace.switch'), ['workspace' => 'professional'])
            ->assertForbidden();
    }

    public function test_login_does_not_follow_an_external_intended_redirect(): void
    {
        $user = User::factory()->create([
            'email' => 'safe-redirect@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('client');

        $this->withSession(['url.intended' => 'https://evil.example/collect'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'Password123!',
            ])
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_rejects_accounts_without_a_workspace_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_user_can_logout_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create(['account_status' => UserAccountStatus::ACTIVE]);
        $user->assignRole('client');

        $this->actingAs($user)
            ->withSession(['private_test_value' => 'must-not-survive'])
            ->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status')
            ->assertSessionMissing('private_test_value');

        $this->assertGuest();
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
}
