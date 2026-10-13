<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalProfileServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_view_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'professional_title' => 'Développeur Laravel',
        ]);

        $this->actingAs($user)
            ->get(route('professional.profile'))
            ->assertOk()
            ->assertSee('Développeur Laravel')
            ->assertViewHas('professional', fn ($view) => $view->is($profile));
    }

    public function test_client_cannot_access_professional_area(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->get(route('professional.profile'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('professional.services.index'))
            ->assertForbidden();
    }

    public function test_services_are_scoped_to_authenticated_professional(): void
    {
        $firstUser = User::factory()->create();
        $firstUser->assignRole('professional');
        $firstProfile = ProfessionalProfile::factory()->create(['user_id' => $firstUser->id]);

        $secondUser = User::factory()->create();
        $secondUser->assignRole('professional');
        $secondProfile = ProfessionalProfile::factory()->create(['user_id' => $secondUser->id]);

        Service::factory()->create([
            'professional_profile_id' => $firstProfile->id,
            'title' => 'Service visible',
        ]);

        $foreignService = Service::factory()->create([
            'professional_profile_id' => $secondProfile->id,
            'title' => 'Service secret',
        ]);

        $this->actingAs($firstUser)
            ->get(route('professional.services.index'))
            ->assertOk()
            ->assertSee('Service visible')
            ->assertDontSee('Service secret');

        $ownedService = Service::where('professional_profile_id', $firstProfile->id)->firstOrFail();

        $this->actingAs($firstUser)
            ->get(route('professional.services.preview', $ownedService))
            ->assertOk()
            ->assertSee('Service visible')
            ->assertSee('Prévisualisation privée');

        $this->actingAs($firstUser)
            ->get(route('professional.services.edit', $foreignService))
            ->assertForbidden();

        $this->actingAs($firstUser)
            ->get(route('professional.services.preview', $foreignService))
            ->assertForbidden();
    }

    public function test_create_and_publish_respects_backend_rules(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post(
            route('professional.services.store'),
            [
                'category_id' => $category->id,
                'title' => 'Développement Laravel',
                'short_description' => 'Application web',
                'description' => 'Création et maintenance d’une application Laravel sécurisée.',
                'pricing_type' => 'fixed',
                'price' => 150,
                'currency' => 'USD',
            ],
        );

        $service = Service::where('professional_profile_id', $profile->id)->firstOrFail();

        $response->assertRedirect(route('professional.services.edit', $service));
        $this->assertSame(ServiceStatus::DRAFT, $service->status);

        $this->actingAs($user)
            ->post(route('professional.services.publish', $service))
            ->assertSessionHasErrors('images');
    }

    public function test_service_scope_fields_are_saved_for_client_transparency(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post(route('professional.services.store'), [
            'category_id' => $category->id,
            'title' => 'Installation électrique',
            'short_description' => 'Installation et vérification de luminaires.',
            'description' => 'Installation de luminaires et vérification du fonctionnement selon le besoin convenu.',
            'pricing_type' => 'fixed',
            'price' => '120.00',
            'currency' => 'USD',
            'included_items' => "Installation des luminaires\nVérification du fonctionnement",
            'excluded_items' => 'Achat des luminaires',
            'client_requirements' => 'Fournir les luminaires avant le rendez-vous.',
            'deliverables' => 'Luminaires installés et testés.',
            'commercial_conditions' => 'Tout travail supplémentaire doit être convenu avant exécution.',
        ])->assertSessionHasNoErrors();

        $service = $profile->services()->firstOrFail();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'included_items' => "Installation des luminaires\nVérification du fonctionnement",
            'excluded_items' => 'Achat des luminaires',
            'client_requirements' => 'Fournir les luminaires avant le rendez-vous.',
            'deliverables' => 'Luminaires installés et testés.',
            'commercial_conditions' => 'Tout travail supplémentaire doit être convenu avant exécution.',
        ]);
    }

    public function test_catalogue_filters_by_category_and_counts_real_service_statuses(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $firstCategory = Category::factory()->create(['name' => 'Informatique', 'status' => 'active']);
        $secondCategory = Category::factory()->create(['name' => 'Plomberie', 'status' => 'active']);

        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $firstCategory->id,
            'title' => 'Site web professionnel',
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $secondCategory->id,
            'title' => 'Réparation de robinet',
            'status' => ServiceStatus::DRAFT,
        ]);

        $this->actingAs($user)
            ->get(route('professional.services.index', ['category_id' => $firstCategory->id]))
            ->assertOk()
            ->assertSee('Site web professionnel')
            ->assertDontSee('Réparation de robinet')
            ->assertViewHas('serviceCounts', function (array $counts): bool {
                return $counts['all'] === 2
                    && $counts['published'] === 1
                    && $counts['draft'] === 1;
            });
    }
}
