<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_search_returns_only_visible_published_services(): void
    {
        $visible = $this->service('Réparation de plomberie', 'plomberie');
        $hidden = $this->service('Service privé', 'prive', [
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
        ]);

        $response = $this->get('/search');

        $response->assertOk()
            ->assertViewIs('public.search-services')
            ->assertSee($visible->title)
            ->assertDontSee($hidden->title);
    }

    public function test_search_filters_by_text_and_category(): void
    {
        $category = Category::factory()->create(['slug' => 'maison']);
        $matching = $this->service('Installation sanitaire', 'installation-sanitaire', [
            'category_id' => $category->id,
        ]);
        $this->service('Création de logo', 'creation-logo');

        $this->get('/search?search=sanitaire&category=maison')
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee('Création de logo');
    }

    public function test_search_paginates_and_preserves_filters(): void
    {
        $category = Category::factory()->create(['slug' => 'travaux']);
        $this->service('Travaux 1', 'travaux-1', ['category_id' => $category->id]);
        $this->service('Travaux 2', 'travaux-2', ['category_id' => $category->id]);

        $this->get('/search?category=travaux&per_page=1&page=2')
            ->assertOk()
            ->assertSee('category=travaux')
            ->assertSee('per_page=1');
    }

    public function test_private_service_detail_is_not_publicly_accessible(): void
    {
        $service = $this->service('Service non publié', 'service-non-publie', [
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
        ]);

        $this->get(route('public.services.show', $service))
            ->assertNotFound();
    }

    public function test_public_professional_directory_renders_the_existing_search_results(): void
    {
        $service = $this->service('Réparation visible', 'reparation-visible');
        $professional = ProfessionalProfile::query()->findOrFail($service->professional_profile_id);

        $this->get(route('public.professionals.index'))
            ->assertOk()
            ->assertViewIs('public.professionals.index')
            ->assertSee($professional->professional_title);
    }

    public function test_public_professional_profile_renders_without_new_controller_variables(): void
    {
        $service = $this->service('Service du profil public', 'service-profil-public');
        $professional = ProfessionalProfile::query()->findOrFail($service->professional_profile_id);

        $this->get(route('public.professionals.show', $professional))
            ->assertOk()
            ->assertViewIs('public.professionals.show')
            ->assertSee($service->title)
            ->assertSee('Avis publiés');
    }

    private function service(string $title, string $slug, array $overrides = []): Service
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            ...array_intersect_key($overrides, array_flip(['status', 'visibility', 'verification_status'])),
        ]);

        return Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $overrides['category_id'] ?? Category::factory()->create()->id,
            'title' => $title,
            'slug' => $slug,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
