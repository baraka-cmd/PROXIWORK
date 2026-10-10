<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Verification;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_pending_can_move_to_under_review_then_verified(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/start-review", [
                'note' => 'Dossier reçu.',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'under_review');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/verify", [
                'note' => 'Documents conformes.',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        $this->assertDatabaseCount('professional_verification_reviews', 2);
        $this->assertDatabaseHas('professional_verification_reviews', [
            'professional_profile_id' => $professional->id,
            'from_status' => 'pending',
            'to_status' => 'under_review',
        ]);
        $this->assertDatabaseHas('professional_verification_reviews', [
            'professional_profile_id' => $professional->id,
            'from_status' => 'under_review',
            'to_status' => 'verified',
        ]);
    }

    public function test_under_review_can_request_additional_information(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.start', $professional), [
                'note' => 'Le dossier a été reçu.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.request-information', $professional), [
                'note' => 'Veuillez ajouter une preuve de qualification lisible.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('professional_profiles', [
            'id' => $professional->id,
            'verification_status' => 'needs_information',
        ]);
        $this->assertDatabaseHas('professional_verification_reviews', [
            'professional_profile_id' => $professional->id,
            'from_status' => 'under_review',
            'to_status' => 'needs_information',
            'reason_code' => 'PROFILE_INCOMPLETE',
        ]);
    }

    public function test_pending_cannot_be_verified_directly(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/verify")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_status']);

        $this->assertDatabaseCount('professional_verification_reviews', 0);
    }

    public function test_pending_cannot_be_rejected_directly(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/reject", [
                'reason_code' => 'DOCUMENT_INVALID',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_status']);
    }

    public function test_under_review_can_be_rejected_with_controlled_reason(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/start-review")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/reject", [
                'reason_code' => 'IDENTITY_MISMATCH',
                'note' => 'Identité non concordante.',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected');

        $this->assertDatabaseHas('professional_verification_reviews', [
            'professional_profile_id' => $professional->id,
            'reason_code' => 'IDENTITY_MISMATCH',
            'to_status' => 'rejected',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.professional.verification.rejected',
            'subject_id' => $professional->id,
        ]);
    }

    public function test_verified_cannot_transition_again(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create([
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/reject", [
                'reason_code' => 'OTHER',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_status']);
    }

    public function test_unauthorized_user_cannot_start_verification(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/start-review")
            ->assertForbidden();
    }

    public function test_reject_reason_is_required_and_controlled(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/start-review")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/verification/reject", [
                'reason_code' => 'INVALID_REASON',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_code']);
    }
}
