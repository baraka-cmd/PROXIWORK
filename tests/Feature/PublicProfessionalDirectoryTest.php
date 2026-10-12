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

class PublicProfessionalDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_verified_public_professional_has_a_public_detail_page(): void
    {
        $professional = $this->professional();
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/professionals')
            ->assertOk()
            ->assertSee('Voir le profil');

        $this->get(route('public.professionals.show', $professional))
            ->assertOk()
            ->assertViewIs('public.professionals.show')
            ->assertSee($professional->user->name)
            ->assertSee($service->title)
            ->assertSee('Aucun avis');
    }

    public function test_unverified_professional_is_not_exposed_in_directory_or_detail(): void
    {
        $professional = $this->professional([
            'verification_status' => ProfessionalVerificationStatus::PENDING,
        ]);
        Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/professionals')
            ->assertOk()
            ->assertDontSee($professional->user->name);

        $this->get(route('public.professionals.show', $professional))
            ->assertNotFound();
    }

    private function professional(array $attributes = []): ProfessionalProfile
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        return ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            ...$attributes,
        ]);
    }
}