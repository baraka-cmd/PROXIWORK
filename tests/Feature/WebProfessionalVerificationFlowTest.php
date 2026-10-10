<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebProfessionalVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_upload_private_evidence_and_resubmit_after_corrections(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole('professional');

        $profile = ProfessionalProfile::factory()->for($user)->create([
            'verification_status' => ProfessionalVerificationStatus::NEEDS_INFORMATION,
        ]);

        $this->actingAs($user)
            ->post(route('professional.documents.store'), [
                'type' => 'certificate',
                'file' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document = ProfessionalDocument::query()
            ->where('professional_profile_id', $profile->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('professional_documents', [
            'id' => $document->id,
            'review_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('professional.verification.resubmit'), ['confirm' => '1'])
            ->assertRedirect(route('professional.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('professional_profiles', [
            'id' => $profile->id,
            'verification_status' => 'pending',
            'visibility' => 'private',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'professional.verification.resubmitted',
            'subject_id' => $profile->id,
        ]);
    }

    public function test_professional_cannot_resubmit_when_no_information_was_requested(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->for($user)->create([
            'verification_status' => ProfessionalVerificationStatus::PENDING,
        ]);

        $this->actingAs($user)
            ->post(route('professional.verification.resubmit'), ['confirm' => '1'])
            ->assertSessionHasErrors('verification_status');
    }
}
