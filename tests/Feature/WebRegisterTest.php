<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_available_to_guests(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('Créez votre compte')
            ->assertSee('Continuer avec Google');
    }

    public function test_guest_can_create_an_active_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Baraka Ntwali',
            'email' => 'baraka@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ]);

        $user = User::where('email', 'baraka@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Baraka Ntwali', $user->name);
        $this->assertSame(UserAccountStatus::ACTIVE, $user->account_status);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        $response->assertRedirect('/');
        $response->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'baraka@example.com']);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Baraka Ntwali',
                'email' => 'baraka@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'terms' => '1',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_requires_strong_password_and_confirmation(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Baraka Ntwali',
                'email' => 'baraka@example.com',
                'password' => 'password',
                'password_confirmation' => 'different',
                'terms' => '1',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['password']);

        $this->assertGuest();
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Baraka Ntwali',
                'email' => 'baraka@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('terms');

        $this->assertGuest();
    }
}
