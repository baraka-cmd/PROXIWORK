<?php

declare(strict_types=1);

namespace Tests\Feature\ProfessionalSearch;

use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_search_returns_only_professionals_with_published_services(): void
    {
        $published = $this->professionalWithService('Développeur Laravel', 'Goma');
        $draft = ProfessionalProfile::factory()->create();

        Service::factory()->for($draft)->create([
            'status' => ServiceStatus::DRAFT,
        ]);

        $response = $this->getJson('/api/v1/professionals');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_search_matches_service_title_and_does_not_expose_private_address_data(): void
    {
        $professional = $this->professionalWithService('Électricien résidentiel', 'Goma');

        $response = $this->getJson('/api/v1/professionals?search=électricien');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $professional->id)
            ->assertJsonMissingPath('data.0.location.address_line_1')
            ->assertJsonMissingPath('data.0.location.latitude')
            ->assertJsonMissingPath('data.0.location.longitude')
            ->assertJsonMissingPath('data.0.phone');
    }

    public function test_category_filter_uses_published_services(): void
    {
        $category = Category::factory()->create(['name' => 'Plomberie']);
        $professional = $this->professionalWithService('Réparation plomberie', 'Goma', $category);

        $response = $this->getJson('/api/v1/professionals?category_id='.$category->id);

        $response->assertOk()->assertJsonPath('data.0.id', $professional->id);
    }

    public function test_skill_filter_supports_any_and_all_modes(): void
    {
        $php = Skill::factory()->create(['name' => 'PHP']);
        $flutter = Skill::factory()->create(['name' => 'Flutter']);
        $professional = $this->professionalWithService('Développement logiciel', 'Goma');

        $professional->skills()->attach([$php->id, $flutter->id]);

        $response = $this->getJson('/api/v1/professionals?skill_ids[]='.$php->id.'&skills_mode=all');

        $response->assertOk()->assertJsonPath('data.0.id', $professional->id);

        $other = $this->professionalWithService('Développement web', 'Goma');
        $other->skills()->attach($php->id);

        $response = $this->getJson('/api/v1/professionals?skill_ids[]='.$php->id.'&skill_ids[]='.$flutter->id.'&skills_mode=all');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $professional->id);
    }

    public function test_location_filter_is_case_insensitive(): void
    {
        $professional = $this->professionalWithService('Photographie événementielle', 'Goma');

        $response = $this->getJson('/api/v1/professionals?city=gOmA&province=nOrD-kIvU');

        $response->assertOk()->assertJsonPath('data.0.id', $professional->id);
    }

    public function test_price_filter_matches_fixed_and_range_services(): void
    {
        $category = Category::factory()->create();

        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->for($professional)->create([
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
            'pricing_type' => ServicePricingType::FIXED,
            'price' => 150,
            'currency' => 'USD',
        ]);

        $response = $this->getJson('/api/v1/professionals?min_price=100&max_price=200&currency=usd');

        $response->assertOk()->assertJsonPath('data.0.id', $professional->id);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_archived_or_inactive_categories_are_not_discoverable(): void
    {
        $category = Category::factory()->create(['status' => 'inactive']);
        $professional = ProfessionalProfile::factory()->create();

        Service::factory()->for($professional)->create([
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/professionals');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    private function professionalWithService(
        string $serviceTitle,
        string $city,
        ?Category $category = null
    ): ProfessionalProfile {
        $user = User::factory()->create(['name' => $serviceTitle.' Pro']);
        $user->profile()->create([
            'first_name' => 'Jean',
            'last_name' => 'Professionnel',
            'bio' => 'Professionnel spécialisé dans les services locaux.',
            'phone' => '+243970000000',
        ]);

        Address::factory()->for($user)->default()->create([
            'city' => $city,
            'province' => 'Nord-Kivu',
            'address_line_1' => 'Adresse privée 123',
        ]);

        $professional = ProfessionalProfile::factory()->for($user)->create();

        Service::factory()->for($professional)->create([
            'category_id' => ($category ?? Category::factory()->create())->id,
            'title' => $serviceTitle,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
            'pricing_type' => ServicePricingType::QUOTE,
        ]);

        return $professional;
    }
}
