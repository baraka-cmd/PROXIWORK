<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        return $user;
    }

    public function test_admin_can_open_verification_center_and_start_review(): void
    {
        $admin = $this->moderator();
        $professional = ProfessionalProfile::factory()->create([
            'verification_status' => ProfessionalVerificationStatus::PENDING,
            'verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.verification.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.start', $professional), [
                'note' => 'Dossier pris en charge.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ProfessionalVerificationStatus::UNDER_REVIEW,
            $professional->refresh()->verification_status,
        );
        $this->assertDatabaseHas('professional_verification_reviews', [
            'professional_profile_id' => $professional->id,
            'admin_user_id' => $admin->id,
            'to_status' => ProfessionalVerificationStatus::UNDER_REVIEW->value,
        ]);
    }

    public function test_admin_can_request_documents_and_resume_review_without_losing_history(): void
    {
        $admin = $this->moderator();
        $professional = ProfessionalProfile::factory()->create([
            'verification_status' => ProfessionalVerificationStatus::PENDING,
            'verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.start', $professional))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.verification.request-documents', $professional), [
                'instructions' => 'Veuillez transmettre une pièce d’identité lisible et un certificat correspondant à votre activité.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('additional_documents_requested', $professional->refresh()->verification_status->value);

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.start', $professional))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(ProfessionalVerificationStatus::UNDER_REVIEW, $professional->refresh()->verification_status);
        $this->assertDatabaseCount('professional_verification_reviews', 3);
    }

    public function test_final_approval_requires_all_latest_documents_to_be_approved(): void
    {
        $admin = $this->moderator();
        $professional = ProfessionalProfile::factory()->create([
            'verification_status' => ProfessionalVerificationStatus::UNDER_REVIEW,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.verify', $professional))
            ->assertSessionHasErrors('documents');

        $document = ProfessionalDocument::query()->create([
            'professional_profile_id' => $professional->getKey(),
            'document_type' => ProfessionalDocument::TYPE_IDENTITY,
            'original_name' => 'identity.pdf',
            'path' => 'verification-files/'.$professional->getKey().'/identity.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => ProfessionalDocument::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.verification.documents.approve', [$professional, $document]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.verify', $professional))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(ProfessionalVerificationStatus::VERIFIED, $professional->refresh()->verification_status);
        $this->assertDatabaseHas('professional_document_reviews', [
            'professional_document_id' => $document->id,
            'admin_user_id' => $admin->id,
            'decision' => ProfessionalDocument::STATUS_APPROVED,
        ]);
    }

    public function test_verification_transition_rejects_invalid_state(): void
    {
        $admin = $this->moderator();
        $professional = ProfessionalProfile::factory()->create([
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.professionals.verification.start', $professional))
            ->assertSessionHasErrors('verification_status');

        $this->assertSame(
            ProfessionalVerificationStatus::VERIFIED,
            $professional->refresh()->verification_status,
        );
    }
}
