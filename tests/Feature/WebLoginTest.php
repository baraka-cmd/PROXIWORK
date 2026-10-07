<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

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

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
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
