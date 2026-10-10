<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\Skill;
use App\Services\ProfessionalService\ProfessionalServiceManager;
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

        $this->publishedService('Développement logiciel');

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
            'billing_unit' => 'hour',
        ]);
        $this->publishedService('Service à 15', [
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '15.00',
            'currency' => 'USD',
            'billing_unit' => 'hour',
        ]);

        $response = $this->get(route('public.search', ['sort' => 'price_low']));

        $response->assertRedirect(route('public.search'));

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee('Choisissez une devise pour filtrer ou comparer les tarifs.');

        $sorted = $this->get(route('public.search', [
            'sort' => 'price_low',
            'currency' => 'USD',
            'billing_unit' => 'hour',
        ]));

        $sorted->assertOk()
            ->assertSee('Service à 15')
            ->assertSee('Service à 50');

        $this->assertLessThan(
            strpos($sorted->getContent(), 'Service à 50'),
            strpos($sorted->getContent(), 'Service à 15')
        );
    }

    public function test_price_sort_never_compares_different_billing_units(): void
    {
        $this->publishedService('Tarif horaire', [
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '15.00',
            'currency' => 'USD',
            'billing_unit' => 'hour',
        ]);
        $this->publishedService('Tarif journalier moins cher en valeur brute', [
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '10.00',
            'currency' => 'USD',
            'billing_unit' => 'day',
        ]);

        $response = $this->get(route('public.search', [
            'sort' => 'price_low',
            'currency' => 'USD',
            'billing_unit' => 'hour',
        ]));

        $response->assertOk()
            ->assertSee('Tarif horaire')
            ->assertDontSee('Tarif journalier moins cher en valeur brute');

        $this->get(route('public.search', [
            'sort' => 'price_low',
            'currency' => 'USD',
        ]))->assertRedirect(route('public.search'));
    }

    public function test_filter_options_only_include_categories_and_skills_with_public_services(): void
    {
        $publicCategory = Category::factory()->create([
            'name' => 'Catégorie disponible publique',
            'slug' => 'categorie-disponible-publique',
        ]);
        Category::factory()->create([
            'name' => 'Catégorie sans offre',
            'slug' => 'categorie-sans-offre',
        ]);
        $service = $this->publishedService('Service avec compétence publique', [
            'category_id' => $publicCategory->getKey(),
        ]);
        $publicSkill = Skill::factory()->create([
            'name' => 'Compétence disponible publique',
            'slug' => 'competence-disponible-publique',
        ]);
        $service->skills()->attach($publicSkill->getKey());
        Skill::factory()->create([
            'name' => 'Compétence sans offre',
            'slug' => 'competence-sans-offre',
        ]);

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee('Catégorie disponible publique')
            ->assertDontSee('Catégorie sans offre')
            ->assertSee('Compétence disponible publique')
            ->assertDontSee('Compétence sans offre');
    }

    public function test_hero_search_preserves_selected_filters_but_resets_pagination(): void
    {
        $category = Category::factory()->create([
            'slug' => 'categorie-preservee-test',
        ]);
        $this->publishedService('Service pour préserver les filtres', [
            'category_id' => $category->getKey(),
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '25.00',
            'currency' => 'USD',
            'billing_unit' => 'hour',
        ]);

        $response = $this->get(route('public.search', [
            'category' => $category->slug,
            'currency' => 'USD',
            'billing_unit' => 'hour',
            'page' => 3,
        ]));

        $response->assertOk()
            ->assertSee('type="hidden" name="category" value="'.$category->slug.'"', false)
            ->assertSee('type="hidden" name="currency" value="USD"', false)
            ->assertSee('type="hidden" name="billing_unit" value="hour"', false)
            ->assertDontSee('type="hidden" name="page" value="3"', false);
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

    public function test_verified_only_filter_uses_the_real_verification_status(): void
    {
        $verified = $this->publishedService('Service professionnel vérifié');
        $verified->professionalProfile->forceFill([
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'rating_average' => '4.80',
            'rating_count' => 5,
        ])->save();

        $this->publishedService('Service professionnel non vérifié');

        $this->get(route('public.search', ['verified_only' => 1]))
            ->assertOk()
            ->assertSee('Service professionnel vérifié')
            ->assertDontSee('Service professionnel non vérifié');
    }

    public function test_service_in_an_inactive_category_is_not_publicly_visible(): void
    {
        $category = Category::factory()->create();
        $service = $this->publishedService('Service catégorie inactive', [
            'category_id' => $category->getKey(),
        ]);

        $category->forceFill(['status' => CategoryStatus::INACTIVE])->save();

        $this->get(route('public.search'))
            ->assertOk()
            ->assertDontSee('Service catégorie inactive');

        $this->get(route('public.services.show', $service->slug))->assertNotFound();
    }

    public function test_public_service_detail_shows_real_pricing_and_the_protected_request_entry_point(): void
    {
        $service = $this->publishedService('Installation électrique', [
            'pricing_type' => ServicePricingType::FIXED,
            'price' => '35.00',
            'currency' => 'USD',
            'billing_unit' => 'hour',
            'service_area' => 'Goma et environs',
        ]);

        $response = $this->get(route('public.services.show', $service->slug));

        $response->assertOk()
            ->assertSee('Installation électrique')
            ->assertSee('35,00 USD / heure')
            ->assertSee('Goma et environs')
            ->assertSee('Demander un devis')
            ->assertSee(route('client.requests.create', ['service' => $service->id]));
    }

    public function test_guest_request_action_redirects_to_login_and_keeps_the_intended_destination(): void
    {
        $service = $this->publishedService('Réparation de téléphone');
        $requestUrl = route('client.requests.create', ['service' => $service->id]);

        $this->get($requestUrl)
            ->assertRedirect(route('login'));

        $this->assertStringContainsString('/client/requests/create?service='.$service->id, (string) session('url.intended'));
    }

    public function test_professional_service_manager_persists_billing_unit_and_service_area(): void
    {
        $professional = ProfessionalProfile::factory()->create();
        $category = Category::factory()->create();

        $service = app(ProfessionalServiceManager::class)->create($professional, [
            'category_id' => $category->getKey(),
            'title' => 'Maintenance informatique',
            'short_description' => 'Maintenance et dépannage informatique.',
            'description' => 'Maintenance et dépannage informatique pour particuliers et petites entreprises.',
            'pricing_type' => ServicePricingType::FIXED->value,
            'price' => '40.00',
            'currency' => 'USD',
            'billing_unit' => 'hour',
            'service_area' => 'Goma et environs',
            'skill_ids' => [],
        ]);

        $this->assertSame('hour', $service->billing_unit);
        $this->assertSame('Goma et environs', $service->service_area);
        $this->assertDatabaseHas('services', [
            'id' => $service->getKey(),
            'billing_unit' => 'hour',
            'service_area' => 'Goma et environs',
        ]);
    }

    public function test_natural_language_search_matches_terms_across_profession_and_service_area(): void
    {
        $service = $this->publishedService('Réparation technique', [
            'service_area' => 'Goma et environs',
        ]);
        $service->professionalProfile->forceFill([
            'professional_title' => 'Plombier professionnel',
        ])->save();

        $this->get(route('public.search', ['search' => 'plombier à Goma']))
            ->assertOk()
            ->assertSee('Réparation technique');
    }

    public function test_search_normalizes_repeated_whitespace(): void
    {
        $this->publishedService('Installation electrique');

        $this->get(route('public.search', ['search' => '  Installation    electrique  ']))
            ->assertOk()
            ->assertSee('Installation electrique');
    }

    public function test_empty_search_results_show_a_helpful_state(): void
    {
        $this->get(route('public.search', ['search' => 'service inexistant xyz']))
            ->assertOk()
            ->assertSee('Aucun service ne correspond à ces critères')
            ->assertSee('Réinitialiser la recherche');
    }

    public function test_unapproved_sort_values_are_rejected_without_affecting_database_queries(): void
    {
        $service = $this->publishedService('Service de sécurité SQL');

        $this->get(route('public.search', ['sort' => 'price_low desc; DROP TABLE services']))
            ->assertRedirect(route('public.search'));

        $this->assertDatabaseHas('services', [
            'id' => $service->getKey(),
            'title' => 'Service de sécurité SQL',
        ]);
    }

    public function test_invalid_budget_criteria_are_preserved_for_correction(): void
    {
        $this->get(route('public.search', ['min_price' => '20']))
            ->assertRedirect(route('public.search'));

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee('value="20"', false)
            ->assertSee('Choisissez une devise pour filtrer ou comparer les tarifs.')
            ->assertSee('Choisissez une unité de facturation pour comparer des tarifs équivalents.');
    }

    public function test_array_search_parameter_is_rejected_without_breaking_the_error_page(): void
    {
        $this->get('/search?search%5B%5D=plombier')
            ->assertRedirect(route('public.search'));

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee('Certains critères de recherche doivent être corrigés.');
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
