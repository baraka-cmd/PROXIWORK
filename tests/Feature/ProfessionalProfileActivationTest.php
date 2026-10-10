<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfessionalProfileActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_add_professional_profile_without_creating_a_second_account(): void
    {
        Notification::fake();

        $user = User::factory()->create(['name' => 'Client Existing']);
        $user->profile()->create([
            'first_name' => 'Client',
            'last_name' => 'Existing',
            'phone' => '+243000000000',
        ]);
        $user->assignRole('client');

        $category = Category::query()->create([
            'name' => 'Plomberie',
            'slug' => 'plomberie-activation',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $skill = Skill::query()->create([
            'name' => 'Installation sanitaire',
            'slug' => 'installation-sanitaire-activation',
            'status' => 'active',
        ]);
        $category->skills()->attach($skill->id);

        $this->actingAs($user)
            ->get(route('account.professional-profile.create'))
            ->assertOk()
            ->assertViewIs('auth.professional-activation')
            ->assertSee('Votre identité sera réutilisée')
            ->assertSee('Ajouter mon espace professionnel');

        $this->actingAs($user)
            ->post(route('account.professional-profile.store'), [
                'business_name' => 'Atelier Client',
                'city' => 'Goma',
                'category_ids' => [$category->id],
                'skill_ids' => [$skill->id],
                'services' => [[
                    'category_id' => $category->id,
                    'title' => 'Installation sanitaire',
                    'description' => 'Installation et réparation de sanitaires pour les clients.',
                    'skill_ids' => [$skill->id],
                    'pricing_type' => 'quote',
                    'currency' => 'CDF',
                ]],
                'terms' => '1',
            ])
            ->assertRedirect(route('professional.dashboard'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue($user->hasRole('professional'));
        $this->assertDatabaseHas('professional_profiles', [
            'user_id' => $user->id,
            'verification_status' => 'pending',
            'status' => 'draft',
            'visibility' => 'private',
        ]);
        $this->assertDatabaseHas('services', [
            'professional_profile_id' => $user->professionalProfile->id,
            'status' => 'draft',
        ]);
        $this->assertSame('professional', session('active_workspace'));
        Notification::assertSentTo($user, AccountActivityNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.web.professional_profile_activated',
        ]);
    }

    public function test_client_cannot_create_a_second_professional_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client', 'professional');
        $user->professionalProfile()->create([
            'city' => 'Goma',
            'status' => 'draft',
            'visibility' => 'private',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('account.professional-profile.create'))
            ->assertStatus(409);
    }

    public function test_professional_activation_rejects_admin_as_a_public_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->post(route('account.professional-profile.store'), [
                'account_type' => 'admin',
                'city' => 'Goma',
                'terms' => '1',
            ])
            ->assertSessionHasErrors('category_ids');

        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertFalse($user->fresh()->hasRole('professional'));
    }
}
