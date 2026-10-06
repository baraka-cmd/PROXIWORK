<?php

declare(strict_types=1);

namespace Tests\Feature\ProfessionalSearch;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfessionalSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_only_public_professionals_with_published_services_are_returned(): void
    {
        $visible = $this->professional([
            'professional_title' => 'Développeur Web & Mobile',
        ]);
        $this->publishedService($visible);

        $hidden = $this->professional([
            'professional_title' => 'Plombier',
        ]);
        Service::factory()->create([
            'professional_profile_id' => $hidden->id,
        ]);

        $response = $this->getJson('/api/v1/professionals')
            ->assertOk();

        $response->assertJsonPath('data.0.id', $visible->id);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_profession_search_filters_by_title(): void
    {
        $developer = $this->professional([
            'professional_title' => 'Développeur Laravel',
        ]);
        $this->publishedService($developer);

        $electrician = $this->professional([
            'professional_title' => 'Électricien bâtiment',
        ]);
        $this->publishedService($electrician);

        $this->getJson('/api/v1/professionals?profession=Laravel')
            ->assertOk()
            ->assertJsonPath('data.0.id', $developer->id);
    }

    public function test_category_skill_and_location_filters_work(): void
    {
        $category = Category::factory()->create(['slug' => 'development']);
        $skill = Skill::factory()->create(['slug' => 'laravel']);

        $professional = $this->professional([
            'professional_title' => 'Développeur Web',
        ]);
        $professional->skills()->attach($skill->id);

        Address::factory()->create([
            'user_id' => $professional->user_id,
            'city' => 'Goma',
            'province' => 'Nord-Kivu',
            'is_default' => true,
        ]);

        $this->publishedService($professional, [
            'category_id' => $category->id,
        ]);

        $other = $this->professional([
            'professional_title' => 'Développeur PHP',
        ]);
        $this->publishedService($other, ['category_id' => $category->id]);

        $this->getJson('/api/v1/professionals?category=development&skill=laravel&city=Goma&province=Nord-Kivu')
            ->assertOk()
            ->assertJsonPath('data.0.id', $professional->id)
            ->assertJsonMissing(['email' => $professional->user->email]);
    }

    public function test_location_filter_uses_the_public_default_address(): void
    {
        $professional = $this->professional();

        Address::factory()->create([
            'user_id' => $professional->user_id,
            'city' => 'Goma',
            'province' => 'Nord-Kivu',
            'is_default' => false,
        ]);
        Address::factory()->create([
            'user_id' => $professional->user_id,
            'city' => 'Bukavu',
            'province' => 'Sud-Kivu',
            'is_default' => true,
        ]);

        $this->publishedService($professional);

        $this->getJson('/api/v1/professionals?city=Goma')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/professionals?city=Bukavu&province=Sud-Kivu')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location.city', 'Bukavu')
            ->assertJsonPath('data.0.location.province', 'Sud-Kivu');
    }

    public function test_skill_mode_can_match_any_or_all_skills(): void
    {
        $laravel = Skill::factory()->create(['slug' => 'laravel']);
        $flutter = Skill::factory()->create(['slug' => 'flutter']);

        $both = $this->professional();
        $both->skills()->attach([$laravel->id, $flutter->id]);
        $this->publishedService($both);

        $laravelOnly = $this->professional();
        $laravelOnly->skills()->attach($laravel->id);
        $this->publishedService($laravelOnly);

        $this->getJson('/api/v1/professionals?skills[]=laravel&skills[]=flutter&skills_mode=any')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/professionals?skills[]=laravel&skills[]=flutter&skills_mode=all')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $both->id);
    }

    public function test_price_and_currency_filters_use_published_service_offers(): void
    {
        $matching = $this->professional();
        $this->publishedService($matching, [
            'pricing_type' => 'fixed',
            'price' => 50,
            'currency' => 'USD',
        ]);

        $outside = $this->professional();
        $this->publishedService($outside, [
            'pricing_type' => 'fixed',
            'price' => 250,
            'currency' => 'USD',
        ]);

        $this->getJson('/api/v1/professionals?min_price=10&max_price=100&currency=USD')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_rating_verification_availability_and_sorting_work(): void
    {
        $verified = $this->professional([
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE,
            'rating_average' => 4.8,
            'rating_count' => 20,
        ]);
        $this->publishedService($verified);

        $pending = $this->professional([
            'verification_status' => ProfessionalVerificationStatus::PENDING,
            'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE,
            'rating_average' => 4.2,
            'rating_count' => 10,
        ]);
        $this->publishedService($pending);

        $this->getJson('/api/v1/professionals?rating=4.5&verification=verified&availability=available&sort=rating')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $verified->id)
            ->assertJsonPath('data.0.rating.average', 4.8);
    }

    public function test_pagination_is_bounded_and_preserves_filters(): void
    {
        $category = Category::factory()->create(['slug' => 'design']);

        for ($i = 0; $i < 18; $i++) {
            $professional = $this->professional();
            $this->publishedService($professional, ['category_id' => $category->id]);
        }

        $response = $this->getJson('/api/v1/professionals?category=design&per_page=10&page=2')
            ->assertOk();

        $this->assertCount(8, $response->json('data'));
        $this->assertSame(18, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame(10, $response->json('meta.per_page'));

        $this->getJson('/api/v1/professionals?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_sort_parameter_is_strictly_whitelisted(): void
    {
        $this->getJson('/api/v1/professionals?sort=rating%20DESC%2Cemail')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_invalid_price_range_is_rejected(): void
    {
        $this->getJson('/api/v1/professionals?min_price=100&max_price=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_price']);
    }

    public function test_public_resource_does_not_expose_private_account_or_address_data(): void
    {
        $professional = $this->professional([
            'professional_title' => 'Développeur',
        ]);
        $this->publishedService($professional);

        Address::factory()->create([
            'user_id' => $professional->user_id,
            'city' => 'Goma',
            'province' => 'Nord-Kivu',
            'contact_phone' => '+243000000000',
            'latitude' => -1.67,
            'longitude' => 29.22,
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/professionals')
            ->assertOk();

        $response->assertJsonMissingPath('data.0.email');
        $response->assertJsonMissingPath('data.0.phone');
        $response->assertJsonMissingPath('data.0.location.latitude');
        $response->assertJsonMissingPath('data.0.location.longitude');
        $response->assertJsonMissingPath('data.0.password');
    }

    public function test_search_avoids_n_plus_one_for_public_result_relations(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $professional = $this->professional();
            $this->publishedService($professional);
            Address::factory()->create([
                'user_id' => $professional->user_id,
                'city' => 'Goma',
                'province' => 'Nord-Kivu',
                'is_default' => true,
            ]);
        }

        DB::enableQueryLog();

        $this->getJson('/api/v1/professionals?per_page=5')
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(15, $queryCount);
    }

    private function professional(array $attributes = []): ProfessionalProfile
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $profile->forceFill($attributes)->save();

        return $profile->refresh();
    }

    private function publishedService(ProfessionalProfile $professional, array $attributes = []): Service
    {
        return Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
            ...$attributes,
        ]);
    }
}
