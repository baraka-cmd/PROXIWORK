<?php

declare(strict_types=1);

namespace Tests\Feature\ProfessionalService;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRealization;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceRealizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_realization_upload_is_private_until_approved_and_owned_by_its_professional(): void
    {
        [$owner, $profile, $service] = $this->professionalService();
        [$otherProfessional] = $this->professionalService();

        $this->actingAs($owner)
            ->post(route('professional.services.realizations.store', $service), [
                'image' => UploadedFile::fake()->image('realization.png'),
                'caption' => 'Installation terminée chez le client',
                'completed_at' => now()->subDay()->toDateString(),
                'participation_confirmed' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $realization = $service->realizations()->firstOrFail();

        $this->assertSame(ServiceRealization::STATUS_PENDING, $realization->status);
        $this->assertTrue($realization->participation_confirmed);
        Storage::disk('local')->assertExists($realization->path);

        $this->get(route('public.service-realizations.image', $realization))
            ->assertNotFound();

        $this->actingAs($otherProfessional)
            ->get(route('professional.services.realizations.file', $realization))
            ->assertForbidden();

        $this->actingAs($otherProfessional)
            ->delete(route('professional.services.realizations.destroy', $realization))
            ->assertForbidden();

        $this->assertDatabaseHas('service_realizations', [
            'id' => $realization->id,
            'status' => ServiceRealization::STATUS_PENDING,
        ]);
    }

    public function test_api_can_list_and_upload_private_service_realizations(): void
    {
        [$owner, $profile, $service] = $this->professionalService();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/realizations', [
                'image' => UploadedFile::fake()->image('api-realization.png'),
                'caption' => 'Travail effectué et testé',
                'participation_confirmed' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ServiceRealization::STATUS_PENDING)
            ->assertJsonPath('data.file_url', route('professional.service-realizations.file', $service->realizations()->value('id')));

        $realizationId = $response->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/professional/services/'.$service->id.'/realizations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $realizationId);

        $this->actingAs($owner, 'sanctum')
            ->get('/api/v1/professional/service-realizations/'.$realizationId.'/file')
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store');
    }

    public function test_admin_approval_makes_realization_public_only_for_a_visible_service(): void
    {
        [$owner, $profile, $service] = $this->professionalService(visible: true);

        $this->actingAs($owner)
            ->post(route('professional.services.realizations.store', $service), [
                'image' => UploadedFile::fake()->image('approved-work.webp'),
                'caption' => 'Réalisation contrôlée',
                'participation_confirmed' => '1',
            ])
            ->assertSessionHasNoErrors();

        $realization = $service->realizations()->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.service-realizations.approve', $realization), [
                'review_note' => 'Image examinée et conforme.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $realization->refresh();

        $this->assertSame(ServiceRealization::STATUS_APPROVED, $realization->status);
        $this->assertSame($admin->id, $realization->reviewed_by);

        $this->get(route('public.service-realizations.image', $realization))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('public.services.show', $service))
            ->assertOk()
            ->assertSee('Réalisations approuvées')
            ->assertSee('Réalisation contrôlée');

        $service->forceFill([
            'status' => ServiceStatus::UNPUBLISHED,
            'published_at' => null,
        ])->save();

        $this->get(route('public.service-realizations.image', $realization))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: ProfessionalProfile, 2: Service}
     */
    private function professionalService(bool $visible = false): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $profileAttributes = ['user_id' => $user->id];

        if ($visible) {
            $profileAttributes += [
                'status' => ProfessionalProfile::STATUS_ACTIVE,
                'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
                'verification_status' => ProfessionalVerificationStatus::VERIFIED,
                'verified_at' => now(),
            ];
        }

        $profile = ProfessionalProfile::factory()->create($profileAttributes);
        $category = Category::factory()->create(['status' => 'active']);
        $service = Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $category->id,
            'status' => $visible ? ServiceStatus::PUBLISHED : ServiceStatus::DRAFT,
            'published_at' => $visible ? now() : null,
        ]);

        return [$user, $profile, $service];
    }
}
