<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicProfessionalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_professional_profile_shows_only_published_public_services(): void
    {
        $visibleService = $this->publishedService('Service public du professionnel');
        $professional = $visibleService->professionalProfile;
        $professional->forceFill(['business_name' => 'Atelier Goma'])->save();

        Service::factory()->create([
            'professional_profile_id' => $professional->getKey(),
            'title' => 'Brouillon privé du profil',
            'slug' => 'brouillon-prive-du-profil',
        ]);

        $url = $this->profileUrl($professional);

        $this->get($url)
            ->assertOk()
            ->assertSee('Atelier Goma')
            ->assertSee('Service public du professionnel')
            ->assertDontSee('Brouillon privé du profil')
            ->assertDontSee($professional->user->email);
    }

    public function test_private_suspended_or_inactive_professional_profiles_are_not_public(): void
    {
        $service = $this->publishedService('Service d’un profil privé');
        $professional = $service->professionalProfile;
        $url = $this->profileUrl($professional);

        $professional->forceFill([
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
        ])->save();

        $this->get($url)->assertNotFound();

        $professional->forceFill([
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'status' => ProfessionalProfile::STATUS_SUSPENDED,
        ])->save();

        $this->get($url)->assertNotFound();

        $professional->forceFill([
            'status' => ProfessionalProfile::STATUS_ACTIVE,
        ])->save();
        $professional->user->forceFill([
            'account_status' => UserAccountStatus::SUSPENDED,
        ])->save();

        $this->get($url)->assertNotFound();
    }

    public function test_professional_profile_uses_a_stable_canonical_url(): void
    {
        $service = $this->publishedService('Service du profil canonique');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Atelier du Nord'])->save();

        $canonicalUrl = $this->profileUrl($professional);

        $this->get(route('public.professionals.show', [
            'professionalProfile' => $professional->getKey(),
            'slug' => 'ancien-nom',
        ]))
            ->assertStatus(301)
            ->assertRedirect($canonicalUrl);
    }

    public function test_service_search_and_professional_directory_link_to_public_profiles(): void
    {
        $service = $this->publishedService('Service relié au profil public');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Entreprise visible'])->save();
        $url = $this->profileUrl($professional);

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee($url, false)
            ->assertSee('Entreprise visible');

        $this->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSee($url, false)
            ->assertSee('Entreprise visible');
    }

    private function profileUrl(ProfessionalProfile $professional): string
    {
        $professional->loadMissing(['user', 'user.profile']);
        $person = $professional->user->profile;
        $personName = $person !== null ? trim($person->first_name.' '.$person->last_name) : '';
        $displayName = filled($professional->business_name)
            ? $professional->business_name
            : (filled($personName) ? $personName : $professional->user->name);
        $slug = Str::slug($displayName) ?: 'professionnel-'.$professional->getKey();

        return route('public.professionals.show', [
            'professionalProfile' => $professional->getKey(),
            'slug' => $slug,
        ]);
    }

    private function publishedService(string $title): Service
    {
        $category = Category::factory()->create();
        $service = Service::factory()->create([
            'category_id' => $category->getKey(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.uniqid(),
            'pricing_type' => ServicePricingType::QUOTE,
            'price' => null,
            'price_min' => null,
            'price_max' => null,
            'currency' => null,
        ]);

        $service->professionalProfile->forceFill([
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalVerificationStatus::PENDING,
        ])->save();

        $service->forceFill([
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ])->save();

        return $service->refresh()->load(['category', 'professionalProfile.user']);
    }
}
