<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalProfileDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_one_professional_profile(): void
    {
        $user = User::factory()->create();
        $profile = ProfessionalProfile::factory()->for($user)->create();

        $this->assertTrue($user->professionalProfile->is($profile));
        $this->assertTrue($profile->user->is($user));
    }

    public function test_professional_profile_has_safe_defaults(): void
    {
        $profile = ProfessionalProfile::factory()->create();

        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->visibility);
        $this->assertSame(
            ProfessionalProfile::VERIFICATION_UNVERIFIED,
            $profile->verification_status,
        );
        $this->assertNull($profile->verified_at);
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = ProfessionalProfile::factory()->for($user)->create();

        $profile->fill([
            'user_id' => $otherUser->id,
            'professional_title' => 'Updated title',
        ]);

        $profile->save();

        $this->assertSame($user->id, $profile->fresh()->user_id);
        $this->assertSame('Updated title', $profile->fresh()->professional_title);
    }

    public function test_sensitive_state_fields_are_not_mass_assignable(): void
    {
        $profile = ProfessionalProfile::factory()->create();

        $profile->fill([
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
        ]);

        $profile->save();

        $fresh = $profile->fresh();

        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $fresh->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $fresh->visibility);
        $this->assertSame(
            ProfessionalProfile::VERIFICATION_UNVERIFIED,
            $fresh->verification_status,
        );
        $this->assertNull($fresh->verified_at);
    }

    public function test_user_cannot_have_two_professional_profiles(): void
    {
        $user = User::factory()->create();

        ProfessionalProfile::factory()->for($user)->create();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        ProfessionalProfile::factory()->for($user)->create();
    }
}
