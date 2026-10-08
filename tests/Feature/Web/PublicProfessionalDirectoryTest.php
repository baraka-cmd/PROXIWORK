<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfessionalDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_is_available_without_authentication(): void
    {
        $this->get(route('public.search'))
            ->assertOk()
            ->assertViewIs('public.search')
            ->assertSeeText('Trouvez le bon professionnel pour votre projet.');
    }

    public function test_professionals_page_is_available_without_authentication(): void
    {
        $this->get(route('public.professionals.index'))
            ->assertOk()
            ->assertViewIs('public.professionals.index')
            ->assertSeeText('Professionnels');
    }

    public function test_professionals_page_uses_published_professional_data(): void
    {
        $user = User::factory()->create(['name' => 'Baraka Ntwali']);
        Profile::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Baraka',
            'last_name' => 'Ntwali',
            'bio' => 'Développeur de solutions web.',
        ]);

        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'professional_title' => 'Développeur Laravel',
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE,
            'rating_average' => 4.80,
            'rating_count' => 12,
        ]);

        Address::factory()->default()->create([
            'user_id' => $user->id,
            'city' => 'Goma',
            'province' => 'Nord-Kivu',
        ]);

        $category = Category::factory()->create(['name' => 'Développement web']);
        $skill = Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        $professional->skills()->attach($skill->id);

        Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'category_id' => $category->id,
            'title' => 'Développement Laravel',
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSeeText('Baraka Ntwali')
            ->assertSeeText('Développeur Laravel')
            ->assertSeeText('Goma, Nord-Kivu')
            ->assertSeeText('Laravel')
            ->assertSeeText('Vérifié')
            ->assertSeeText('Disponible');
    }

    public function test_search_filters_professionals_by_profession(): void
    {
        $matchingUser = User::factory()->create(['name' => 'Match']);
        $matchingProfessional = ProfessionalProfile::factory()->create([
            'user_id' => $matchingUser->id,
            'professional_title' => 'Développeur Laravel',
        ]);
        $category = Category::factory()->create();
        Service::factory()->create([
            'professional_profile_id' => $matchingProfessional->id,
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $otherUser = User::factory()->create(['name' => 'Other']);
        $otherProfessional = ProfessionalProfile::factory()->create([
            'user_id' => $otherUser->id,
            'professional_title' => 'Graphiste',
        ]);
        Service::factory()->create([
            'professional_profile_id' => $otherProfessional->id,
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get(route('public.search', ['profession' => 'Laravel']))
            ->assertOk()
            ->assertSeeText('Développeur Laravel')
            ->assertDontSeeText('Graphiste');
    }
}
