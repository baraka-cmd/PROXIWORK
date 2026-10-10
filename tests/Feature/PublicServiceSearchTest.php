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
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_public_and_lists_only_publicly_visible_services(): void
    {
        $visible = $this->publishedService('Réparation de plomberie');
        $draft = Service::factory()->create([
            'title' => 'Service encore en brouillon',
            'slug' => 'service-brouillon-test',
        ]);

        $response = $this->get(route('public.search'));

        $response->assertOk()
            ->assertSee('Réparation de plomberie')
            ->assertDontSee('Service encore en brouillon')
            ->assertSee(route('public.services.show', $visible->slug));
    }

    public function test_text_category_skill_and_location_filters_are_combined(): void
    {
        $category = Category::factory()->create([
            'name' => 'Maison et bâtiment',
            'slug' => 'maison-batiment-test',
        ]);
        $service = $this->publishedService('Réparation de plomberie', [
            'category_id' => $category->id,
            'city' => 'Goma',
            'province' => 'Nord-Kivu',
        ]);
        $skill = Skill::factory()->create([
            'name' => 'Plomberie',
            'slug' => 'plomberie-test',
        ]);
        $service->skills()->attach($skill->getKey());

        $other = $this->publishedService('Développement logiciel');

        $response = $this->get(route('public.search', [
            'search' => 'plomberie',
            'category' => $category->slug,
            'skills' => [$skill->slug],
            'city' => 'Goma',
            'province' => 'Nord-Kivu',
        ]));

        $response->assertOk()
            ->assertSee('Réparation de plomberie')
            ->assertDontSee('Développement logiciel');
    }

    public function test_services_owned_by_private_suspended_or_inactive_accounts_are_hidden(): void
    {
        $privateService = $this->publishedService('Offre profil privé');
        $privateService->professionalProfile->forceFill([
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
        ])->save();

        $suspendedService = $this->publishedService('Offre profil suspendu');
        $suspendedService->professionalProfile->forceFill([
            'status' => ProfessionalProfile::STATUS_SUSPENDED,
        ])->save();

        $inactiveAccountService = $this->publishedService('Offre compte suspendu');
        $inactiveAccountService->professionalProfile->user->forceFill([
            'account_status' => UserAccountStatus::SUSPENDED,
        ])->save();

        $this->get(route('public.search'))
            ->assertOk()
            ->assertDontSee('Offre profil privé')
            ->assertDontSee('Offre profil suspendu')
            ->assertDontSee('Offre compte suspendu');

        $this->get(route('public.services.show', $privateService->slug))->assertNotFound();
        $this->getJson('/api/v1/services/'.$inactiveAccountService->getKey())->assertNotFound();
    }

    public function test_unpublished_service_is_not_exposed_by_public_service_api(): void
    {
        $draft = Service::factory()->create([
            'title' => 'Prestation privée API',
            'slug' => 'prestation-privee-api-test',
        ]);

        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Prestation privée API']);

        $this->getJson('/api/v1/services/'.$draft->getKey())->assertNotFound();
    }

    public function test_price_sort_requires_currency_and_returns_a_clean_search_page(): void
    {
        $this->publishedService('Service à 50', [
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '50.00',
            'currency' => 'USD',
        ]);
        $this->publishedService('Service à 15', [
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '15.00',
            'currency' => 'USD',
        ]);

        $response = $this->get(route('public.search', ['sort' => 'price_low']));

        $response->assertRedirect(route('public.search'));

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee('Choisissez une devise pour filtrer ou comparer les tarifs.');

        $sorted = $this->get(route('public.search', [
            'sort' => 'price_low',
            'currency' => 'USD',
        ]));

        $sorted->assertOk()
            ->assertSee('Service à 15')
            ->assertSee('Service à 50');

        $this->assertLessThan(
            strpos($sorted->getContent(), 'Service à 50'),
            strpos($sorted->getContent(), 'Service à 15')
        );
    }

    public function test_pagination_keeps_search_filters_and_catalogue_alias_redirects_to_search(): void
    {
        $category = Category::factory()->create([
            'slug' => 'services-test-pagination',
        ]);

        $this->publishedService('Premier service', ['category_id' => $category->id]);
        $this->publishedService('Deuxième service', ['category_id' => $category->id]);

        $response = $this->get(route('public.search', [
            'category' => $category->slug,
            'per_page' => 1,
        ]));

        $response->assertOk()
            ->assertSee('category='.$category->slug, false)
            ->assertSee('per_page=1', false);

        $this->get(route('public.services.index', ['search' => 'plomberie']))
            ->assertRedirect(route('public.search', ['search' => 'plomberie']));
    }

    private function publishedService(string $title, array $attributes = []): Service
    {
        $category = isset($attributes['category_id'])
            ? Category::query()->findOrFail($attributes['category_id'])
            : Category::factory()->create();

        $serviceAttributes = collect($attributes)->except(['city', 'province', 'verification_status'])->all();
        $service = Service::factory()->create(array_merge([
            'category_id' => $category->getKey(),
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'pricing_type' => ServicePricingType::QUOTE,
            'price' => null,
            'price_min' => null,
            'price_max' => null,
            'currency' => null,
        ], $serviceAttributes));

        $professional = $service->professionalProfile;
        $profileAttributes = collect($attributes)
            ->only(['city', 'province', 'verification_status'])
            ->all();
        $professional->forceFill(array_merge([
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalVerificationStatus::PENDING,
        ], $profileAttributes))->save();

        $service->forceFill([
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ])->save();

        return $service->refresh()->load(['category', 'professionalProfile.user']);
    }
}
