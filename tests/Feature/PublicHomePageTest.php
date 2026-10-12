<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_proxiwork_public_experience(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('public.home-dynamic')
            ->assertSee('PROXIWORK')
            ->assertSee('Rechercher')
            ->assertSee('name="search"', false)
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }

    public function test_homepage_uses_published_service_data(): void
    {
        $user = User::factory()->create(['name' => 'Professionnel visible']);
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
        ]);
        $category = Category::factory()->create(['name' => 'Maison']);
        $service = Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $category->id,
            'title' => 'Installation sanitaire réelle',
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/')
            ->assertSee($service->title)
            ->assertSee($category->name)
            ->assertSee('Professionnel visible');
    }

    public function test_homepage_excludes_unverified_professionals(): void
    {
        $user = User::factory()->create(['name' => 'Professionnel privé']);
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ProfessionalVerificationStatus::PENDING,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
        ]);
        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Professionnel privé');
    }
}
