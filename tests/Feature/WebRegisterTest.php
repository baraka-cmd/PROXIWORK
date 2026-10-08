<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_register_page_is_available_to_guests(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('Créez votre compte')
            ->assertSee('Continuer avec Google')
            ->assertSee('name="account_type"', false)
            ->assertSee('Client — je recherche des services')
            ->assertSee('Professionnel — je propose mes services');
    }

    public function test_guest_can_create_an_active_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Baraka Ntwali',
            'email' => 'baraka@example.com',
            'account_type' => 'client',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ]);

        $user = User::where('email', 'baraka@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Baraka Ntwali', $user->name);
        $this->assertSame(UserAccountStatus::ACTIVE, $user->account_status);
        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseMissing('professional_profiles', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
    }

    public function test_professional_registration_assigns_role_and_creates_pending_profile(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Professionnel Test',
            'email' => 'professional@example.com',
            'account_type' => 'professional',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ]);

        $user = User::where('email', 'professional@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('professional'));
        $this->assertFalse($user->hasRole('client'));
        $this->assertDatabaseHas('professional_profiles', [
            'user_id' => $user->id,
            'verification_status' => 'pending',
        ]);
        $this->assertSame(1, ProfessionalProfile::query()->where('user_id', $user->id)->count());

        $response->assertRedirect(route('professional.dashboard'));
        $response->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_privileged_or_unknown_account_types(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Tentative Admin',
                'email' => 'attempt@example.com',
                'account_type' => 'admin',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'terms' => '1',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('account_type');

        $this->assertDatabaseMissing('users', ['email' => 'attempt@example.com']);
        $this->assertGuest();
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'baraka@example.com']);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Baraka Ntwali',
                'email' => 'baraka@example.com',
                'account_type' => 'client',
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
                'account_type' => 'client',
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
