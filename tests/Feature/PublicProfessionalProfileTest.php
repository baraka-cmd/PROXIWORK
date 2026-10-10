<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
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
        $professional->user->forceFill(['email' => 'private-professional@example.test'])->save();

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
            ->assertDontSee('private-professional@example.test');
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

    public function test_service_search_links_to_public_professional_profiles(): void
    {
        $service = $this->publishedService('Service relié au profil public');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Entreprise visible'])->save();
        $url = $this->profileUrl($professional);

        $this->get(route('public.search'))
            ->assertOk()
            ->assertSee('Service relié au profil public')
            ->assertSee('Entreprise visible')
            ->assertSee($url, false);
    }

    public function test_professional_directory_links_to_public_professional_profiles(): void
    {
        $service = $this->publishedService('Service de l’annuaire public');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Entreprise annuaire'])->save();
        $url = $this->profileUrl($professional);

        $this->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSee($url, false)
            ->assertSee('Entreprise annuaire');
    }

    public function test_guest_favorite_link_preserves_the_public_profile_as_the_login_return_target(): void
    {
        $service = $this->publishedService('Service pour retour de connexion');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Atelier retour connexion'])->save();
        $profilePath = parse_url($this->profileUrl($professional), PHP_URL_PATH);
        $loginUrl = route('login', ['return_to' => $profilePath]);

        $this->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSee($loginUrl, false)
            ->assertSee('Connectez-vous pour ajouter Atelier retour connexion aux favoris');

        $this->get($loginUrl)->assertOk();

        $this->assertSame($this->profileUrl($professional), session('url.intended'));
    }

    public function test_login_ignores_external_return_targets(): void
    {
        $this->get(route('login', ['return_to' => 'https://example.invalid/']))
            ->assertOk();

        $this->assertNull(session('url.intended'));
    }

    public function test_authenticated_client_sees_their_favorite_state_on_directory_cards(): void
    {
        $this->seed(RbacSeeder::class);
        $service = $this->publishedService('Service avec favori personnalisé');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Atelier favori personnalisé'])->save();
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSee('aria-pressed="false"', false);

        Favorite::query()->create([
            'user_id' => $client->getKey(),
            'professional_profile_id' => $professional->getKey(),
        ]);

        $this->actingAs($client)
            ->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('Retirer Atelier favori personnalisé des favoris');
    }

    public function test_favorited_directory_card_submits_delete_for_the_current_users_favorite(): void
    {
        $this->seed(RbacSeeder::class);
        $service = $this->publishedService('Service pour retirer un favori');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Atelier à retirer'])->save();
        $client = User::factory()->create();
        $client->assignRole('client');

        $favorite = Favorite::query()->create([
            'user_id' => $client->getKey(),
            'professional_profile_id' => $professional->getKey(),
        ]);

        $this->actingAs($client)
            ->get(route('public.professionals.index'))
            ->assertOk()
            ->assertSee(route('client.favorites.destroy', $favorite->getKey()), false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('aria-pressed="true"', false);

        $this->actingAs($client)
            ->delete(route('client.favorites.destroy', $favorite->getKey()))
            ->assertRedirect();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->getKey()]);
    }

    public function test_verification_badge_requires_an_effective_approval_timestamp(): void
    {
        $service = $this->publishedService('Service vérification effective');
        $professional = $service->professionalProfile;
        $professional->forceFill([
            'business_name' => 'Profil sans validation effective',
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
            'verified_at' => null,
        ])->save();

        $this->get($this->profileUrl($professional))
            ->assertOk()
            ->assertDontSee('Professionnel vérifié');
    }

    public function test_public_profile_allows_a_client_to_add_and_remove_a_favorite(): void
    {
        $this->seed(RbacSeeder::class);
        $service = $this->publishedService('Service profil avec favori');
        $professional = $service->professionalProfile;
        $professional->forceFill(['business_name' => 'Atelier profil favori'])->save();
        $client = User::factory()->create();
        $client->assignRole('client');
        $url = $this->profileUrl($professional);

        $this->actingAs($client)
            ->get($url)
            ->assertOk()
            ->assertSee('Ajouter aux favoris');

        $this->actingAs($client)
            ->put(route('client.favorites.store', $professional->getKey()))
            ->assertRedirect();

        $favorite = Favorite::query()
            ->where('user_id', $client->getKey())
            ->where('professional_profile_id', $professional->getKey())
            ->firstOrFail();

        $this->actingAs($client)
            ->get($url)
            ->assertOk()
            ->assertSee('Retirer des favoris')
            ->assertSee(route('client.favorites.destroy', $favorite->getKey()), false);

        $this->actingAs($client)
            ->delete(route('client.favorites.destroy', $favorite->getKey()))
            ->assertRedirect();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->getKey()]);
    }

    public function test_favorites_cannot_be_added_for_a_suspended_professional(): void
    {
        $this->seed(RbacSeeder::class);
        $service = $this->publishedService('Service profil suspendu');
        $professional = $service->professionalProfile;
        $professional->forceFill(['status' => ProfessionalProfile::STATUS_SUSPENDED])->save();
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->put(route('client.favorites.store', $professional->getKey()))
            ->assertForbidden();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $client->getKey(),
            'professional_profile_id' => $professional->getKey(),
        ]);
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
