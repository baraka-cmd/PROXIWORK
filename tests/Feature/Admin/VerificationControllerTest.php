<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProfessionalVerificationStatus;
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
        $professional = ProfessionalProfile::factory()->create();

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
