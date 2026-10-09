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

        $this->actingAs($firstUser)
            ->get(route('professional.services.edit', $foreignService))
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
}
