<?php

declare(strict_types=1);

namespace Tests\Feature\ProfessionalService;

use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfessionalServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Storage::fake('public');
    }

    public function test_another_professional_cannot_delete_a_service_image(): void
    {
        [$owner, $profile] = $this->professional();
        [$attacker] = $this->professional();

        $service = Service::factory()->create([
            'professional_profile_id' => $profile->id,
        ]);

        $image = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                'image' => UploadedFile::fake()->image('cover.webp'),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($attacker, 'sanctum')
            ->deleteJson('/api/v1/professional/service-images/'.$image)
            ->assertForbidden();

        $this->assertDatabaseHas('service_images', ['id' => $image]);
    }

    public function test_service_image_upload_rejects_non_image_files(): void
    {
        [$professional, $profile] = $this->professional();
        $service = Service::factory()->create([
            'professional_profile_id' => $profile->id,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->id.'/images', [
                'image' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertDatabaseCount('service_images', 0);
    }

    public function test_professional_service_endpoints_require_authentication(): void
    {
        $service = Service::factory()->create();

        $this->getJson('/api/v1/professional/services')->assertUnauthorized();
        $this->getJson('/api/v1/professional/services/'.$service->id)->assertUnauthorized();
        $this->postJson('/api/v1/professional/services/'.$service->id.'/publish')->assertUnauthorized();
    }

    private function professional(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        return [$user, $profile];
    }
}
