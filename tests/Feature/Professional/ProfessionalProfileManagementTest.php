<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_open_and_update_own_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = $user->professionalProfile()->create();

        $this->actingAs($user)
            ->get(route('professional.profile.edit'))
            ->assertOk()
            ->assertViewIs('professional/profile-edit');

        $this->actingAs($user)
            ->patch(route('professional.profile.update'), [
                'professional_title' => 'Développeur Web',
                'description' => 'Développement web et applications métier.',
                'years_experience' => 5,
                'starting_price' => '50.00',
                'currency' => 'usd',
                'province' => 'Nord-Kivu',
                'city' => 'Goma',
                'commune' => 'Karisimbi',
                'service_radius_km' => 25,
                'status' => 'active',
                'visibility' => 'public',
                'verification_status' => 'verified',
            ])
            ->assertRedirect(route('professional.profile'))
            ->assertSessionHas('success');

        $profile->refresh();

        $this->assertSame('Développeur Web', $profile->professional_title);
        $this->assertSame('Goma', $profile->city);
        $this->assertSame('Nord-Kivu', $profile->province);
        $this->assertSame('USD', $profile->currency);
        $this->assertSame('50.00', $profile->starting_price);
        $this->assertSame(5, $profile->years_experience);
        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->visibility);
        $this->assertSame(ProfessionalVerificationStatus::PENDING, $profile->verification_status);
    }

    public function test_client_cannot_access_professional_profile_editor(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('professional.profile.edit'))
            ->assertForbidden();
    }

    public function test_coordinates_must_be_provided_as_a_pair(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $user->professionalProfile()->create();

        $this->actingAs($user)
            ->from(route('professional.profile.edit'))
            ->patch(route('professional.profile.update'), [
                'latitude' => '-1.67',
            ])
            ->assertRedirect(route('professional.profile.edit'))
            ->assertSessionHasErrors(['longitude']);
    }

    public function test_new_professional_profile_uses_private_safe_defaults(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = $user->professionalProfile()->create();

        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->visibility);
        $this->assertSame(ProfessionalVerificationStatus::PENDING, $profile->verification_status);
    }
}
