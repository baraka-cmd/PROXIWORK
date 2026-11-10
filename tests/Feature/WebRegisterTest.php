<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ServiceExecutionMode;
use App\Enums\ServicePricingType;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Skill;
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

    public function test_register_page_offers_separate_client_and_professional_paths(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('Quel est votre besoin ?')
            ->assertSee('Je recherche un professionnel')
            ->assertSee('Je souhaite proposer mes services')
            ->assertSee(route('register.client'), false)
            ->assertSee(route('register.professional'), false);
    }

    public function test_guest_can_create_an_active_client_account(): void
    {
        $response = $this->post(route('register.client.store'), [
            'first_name' => 'Baraka',
            'last_name' => 'Ntwali',
            'phone' => '+243 000 000 000',
            'email' => 'Baraka@Example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ]);

        $user = User::query()->where('email', 'baraka@example.com')->firstOrFail();

        $this->assertSame('Baraka Ntwali', $user->name);
        $this->assertSame(UserAccountStatus::ACTIVE, $user->account_status);
        $this->assertTrue($user->hasRole('client'));
        $this->assertFalse($user->hasRole('professional'));
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Baraka',
            'last_name' => 'Ntwali',
            'phone' => '+243 000 000 000',
        ]);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('professional_profiles', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHas('success');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_professional_registration_creates_a_private_pending_profile_and_draft_service(): void
    {
        $category = Category::factory()->create(['status' => 'active', 'parent_id' => null]);
        $skill = Skill::factory()->create(['category_id' => $category->id, 'status' => 'active']);

        $response = $this->post(route('register.professional.store'), [
            'first_name' => 'Professionnel',
            'last_name' => 'Test',
            'phone' => '+243 000 000 001',
            'email' => 'professional@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
            'professional_title' => 'Électricien bâtiment',
            'business_name' => 'Atelier Test',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'service_radius_km' => 25,
            'years_experience' => 3,
            'category_ids' => [$category->id],
            'skill_ids' => [$skill->id],
            'services' => [[
                'category_id' => $category->id,
                'skill_ids' => [$skill->id],
                'title' => 'Installation électrique résidentielle',
                'description' => 'Installation, contrôle et entretien des équipements électriques résidentiels.',
                'pricing_type' => ServicePricingType::FIXED->value,
                'price' => '100.00',
                'currency' => 'USD',
                'execution_mode' => ServiceExecutionMode::REMOTE->value,
                'estimated_duration_minutes' => 60,
            ]],
        ]);

        $user = User::query()->where('email', 'professional@example.com')->firstOrFail();
        $profile = ProfessionalProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertTrue($user->hasRole('professional'));
        $this->assertFalse($user->hasRole('client'));
        $this->assertSame('pending', $profile->verification_status->value);
        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->visibility);
        $this->assertDatabaseHas('services', [
            'professional_profile_id' => $profile->id,
            'status' => 'draft',
        ]);

        $response->assertRedirect(route('professional.dashboard'));
        $response->assertSessionHas('success');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_client_registration_cannot_escalate_to_an_administrative_role(): void
    {
        $this->post(route('register.client.store'), [
            'first_name' => 'Tentative',
            'last_name' => 'Admin',
            'phone' => '+243 000 000 002',
            'email' => 'attempt@example.com',
            'account_type' => 'admin',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ])->assertRedirect(route('client.dashboard'));

        $user = User::query()->where('email', 'attempt@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_client_registration_role_is_selected_by_the_server_route_not_the_payload(): void
    {
        $this->post(route('register.client.store'), [
            'first_name' => 'Client',
            'last_name' => 'Route',
            'phone' => '+243 000 000 003',
            'email' => 'client-route@example.com',
            'account_type' => 'professional',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ])->assertRedirect(route('client.dashboard'));

        $user = User::query()->where('email', 'client-route@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
        $this->assertFalse($user->hasRole('professional'));
        $this->assertDatabaseMissing('professional_profiles', ['user_id' => $user->id]);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'baraka@example.com']);

        $this->from(route('register.client'))
            ->post(route('register.client.store'), [
                'first_name' => 'Baraka',
                'last_name' => 'Ntwali',
                'phone' => '+243 000 000 004',
                'email' => 'baraka@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'terms' => '1',
            ])
            ->assertRedirect(route('register.client'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_requires_a_strong_password_and_confirmation(): void
    {
        $this->from(route('register.client'))
            ->post(route('register.client.store'), [
                'first_name' => 'Baraka',
                'last_name' => 'Ntwali',
                'phone' => '+243 000 000 005',
                'email' => 'weak-password@example.com',
                'password' => 'password',
                'password_confirmation' => 'different',
                'terms' => '1',
            ])
            ->assertRedirect(route('register.client'))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->from(route('register.client'))
            ->post(route('register.client.store'), [
                'first_name' => 'Baraka',
                'last_name' => 'Ntwali',
                'phone' => '+243 000 000 006',
                'email' => 'terms@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('register.client'))
            ->assertSessionHasErrors('terms');

        $this->assertGuest();
    }
}
