<?php

declare(strict_types=1);

namespace Tests\Feature\ProfessionalService;

use App\Enums\CategoryStatus;
use App\Enums\ServiceStatus;
use App\Enums\SkillStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfessionalServiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Storage::fake('public');
    }

    public function test_professional_can_create_and_update_own_service(): void
    {
        [$professional, $profile] = $this->professional();
        $category = Category::factory()->create();
        $skill = Skill::factory()->create();

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services', [
                'category_id' => $category->id,
                'title' => 'Développement Laravel',
                'description' => 'Je développe des applications Laravel robustes et maintenables.',
                'pricing_type' => 'fixed',
                'price' => 150,
                'currency' => 'usd',
                'skill_ids' => [$skill->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Développement Laravel')
            ->assertJsonPath('data.currency', 'USD');

        $serviceId = $response->json('data.id');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'professional_profile_id' => $profile->id,
            'slug' => 'developpement-laravel',
            'status' => ServiceStatus::DRAFT->value,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->patchJson('/api/v1/professional/services/'.$serviceId, [
                'title' => 'Développement Laravel professionnel',
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'developpement-laravel');
    }

    public function test_professional_cannot_access_another_professionals_service(): void
    {
        [$professionalA] = $this->professional();
        [$professionalB, $profileB] = $this->professional();
        $service = Service::factory()->create(['professional_profile_id' => $profileB->id]);

        $this->actingAs($professionalA, 'sanctum')
            ->getJson('/api/v1/professional/services/'.$service->id)
            ->assertForbidden();

        $this->actingAs($professionalA, 'sanctum')
            ->patchJson('/api/v1/professional/services/'.$service->id, ['title' => 'Tentative'])
            ->assertForbidden();
    }

    public function test_client_cannot_use_professional_service_api(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professional/services', [])
            ->assertForbidden();
    }

    public function test_invalid_pricing_is_rejected(): void
    {
        [$professional] = $this->professional();
        $category = Category::factory()->create();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services', [
                'category_id' => $category->id,
                'title' => 'Service à fourchette',
                'description' => 'Description suffisamment longue pour la validation.',
                'pricing_type' => 'range',
                'price_min' => 500,
                'price_max' => 100,
                'currency' => 'USD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_max']);
    }

    public function test_inactive_category_and_skill_are_rejected(): void
    {
        [$professional] = $this->professional();
        $category = Category::factory()->create(['status' => CategoryStatus::ARCHIVED]);
        $skill = Skill::factory()->create(['status' => SkillStatus::ARCHIVED]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services', [
                'category_id' => $category->id,
                'title' => 'Service avec catalogue invalide',
                'description' => 'Description suffisamment longue pour la validation.',
                'pricing_type' => 'quote',
                'skill_ids' => [$skill->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'skill_ids']);
    }

    public function test_duplicate_skill_ids_are_rejected(): void
    {
        [$professional] = $this->professional();
        $category = Category::factory()->create();
        $skill = Skill::factory()->create();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services', [
                'category_id' => $category->id,
                'title' => 'Service avec doublon',
                'description' => 'Description suffisamment longue pour la validation.',
                'pricing_type' => 'quote',
                'skill_ids' => [$skill->id, $skill->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skill_ids.1']);
    }

    public function test_published_service_is_public_but_draft_is_not(): void
    {
        $service = Service::factory()->create();

        $this->getJson('/api/v1/services/'.$service->id)->assertNotFound();

        $service->forceFill([
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ])->save();

        $this->getJson('/api/v1/services/'.$service->id)
            ->assertOk()
            ->assertJsonPath('data.id', $service->id);
    }

    public function test_public_services_can_be_searched_and_filtered(): void
    {
        $category = Category::factory()->create();
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'title' => 'Application Flutter mobile',
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        Service::factory()->create([
            'category_id' => $category->id,
            'title' => 'Plomberie',
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/services?search=Flutter&category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $service->id);
    }

    public function test_publishing_requires_a_cover_image(): void
    {
        [$professional] = $this->professional();
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->professionalProfile->id,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images']);
    }

    public function test_professional_can_publish_and_unpublish_service(): void
    {
        [$professional] = $this->professional();
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->professionalProfile->id,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('cover.webp'),
                'alt_text' => 'Illustration du service',
            ])
            ->assertCreated();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'unpublished');
    }

    public function test_service_images_are_owned_and_cover_is_protected(): void
    {
        [$professionalA] = $this->professional();
        [$professionalB] = $this->professional();
        $serviceA = Service::factory()->create(['professional_profile_id' => $professionalA->professionalProfile->id]);
        $serviceB = Service::factory()->create(['professional_profile_id' => $professionalB->professionalProfile->id]);

        $image = $this->actingAs($professionalA, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$serviceA->id.'/images', [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('cover.webp'),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($professionalB, 'sanctum')
            ->patchJson('/api/v1/professional/service-images/'.$image, ['is_cover' => false])
            ->assertForbidden();

        $this->actingAs($professionalA, 'sanctum')
            ->patchJson('/api/v1/professional/service-images/'.$image, ['is_cover' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_cover']);

        $this->assertDatabaseHas('service_images', [
            'id' => $image,
            'service_id' => $serviceA->id,
            'is_cover' => true,
        ]);

        $this->assertNotSame($serviceA->id, $serviceB->id);
    }

    public function test_service_is_limited_to_eight_images(): void
    {
        [$professional] = $this->professional();
        $service = Service::factory()->create(['professional_profile_id' => $professional->professionalProfile->id]);

        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($professional, 'sanctum')
                ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                    'image' => \Illuminate\Http\UploadedFile::fake()->image('image-'.$i.'.webp'),
                ])
                ->assertCreated();
        }

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('ninth.webp'),
            ])
            ->assertUnprocessable();
    }

    public function test_deleting_cover_promotes_next_image(): void
    {
        [$professional] = $this->professional();
        $service = Service::factory()->create(['professional_profile_id' => $professional->professionalProfile->id]);

        $first = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('first.webp'),
            ])
            ->json('data.id');

        $second = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('second.webp'),
            ])
            ->json('data.id');

        $this->actingAs($professional, 'sanctum')
            ->deleteJson('/api/v1/professional/service-images/'.$first)
            ->assertNoContent();

        $this->assertDatabaseHas('service_images', [
            'id' => $second,
            'is_cover' => true,
        ]);
    }

    public function test_archiving_service_removes_it_from_public_catalog(): void
    {
        [$professional] = $this->professional();
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->professionalProfile->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->deleteJson('/api/v1/professional/services/'.$service->id)
            ->assertNoContent();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => ServiceStatus::ARCHIVED->value,
        ]);

        $this->getJson('/api/v1/services/'.$service->id)->assertNotFound();
    }

    public function test_public_service_under_inactive_category_is_hidden(): void
    {
        $category = Category::factory()->create(['status' => CategoryStatus::INACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/services/'.$service->id)->assertNotFound();
    }

    public function test_professional_listing_is_scoped_to_authenticated_professional(): void
    {
        [$professionalA] = $this->professional();
        [$professionalB] = $this->professional();

        Service::factory()->create(['professional_profile_id' => $professionalA->professionalProfile->id]);
        Service::factory()->create(['professional_profile_id' => $professionalB->professionalProfile->id]);

        $this->actingAs($professionalA, 'sanctum')
            ->getJson('/api/v1/professional/services')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_professional_service_query_is_bounded(): void
    {
        [$professional] = $this->professional();

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/v1/professional/services?per_page=101')
            ->assertUnprocessable();
    }

    private function professional(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        return [$user, $profile];
    }
}
