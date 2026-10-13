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
            ->assertViewIs('professional.profile-edit');

        $this->actingAs($user)
            ->patch(route('professional.profile.update'), [
                'first_name' => 'Baraka',
                'last_name' => 'Ntwali',
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
                'visibility' => 'private',
                'verification_status' => 'verified',
                'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE->value,
            ])
            ->assertRedirect(route('professional.profile'))
            ->assertSessionHas('success');

        $profile->refresh();

        $this->assertSame('Développeur Web', $profile->professional_title);
        $this->assertSame(ProfessionalAvailabilityStatus::AVAILABLE, $profile->refresh()->availability_status);
        $this->assertSame('Baraka Ntwali', $user->fresh()->name);
        $this->assertSame('Baraka', $user->profile()->value('first_name'));
        $this->assertSame('Ntwali', $user->profile()->value('last_name'));
        $this->assertSame('Goma', $profile->city);
        $this->assertSame('Nord-Kivu', $profile->province);
        $this->assertSame('USD', $profile->currency);
        $this->assertSame('50.00', $profile->starting_price);
        $this->assertSame(5, $profile->years_experience);
        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->visibility);
        $this->assertSame(ProfessionalVerificationStatus::PENDING, $profile->verification_status);
    }

    public function test_professional_can_manage_skills_and_skill_metadata(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = $user->professionalProfile()->create();
        $skill = Skill::factory()->create(['name' => 'Laravel', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('professional.profile.skills.update'), [
                'skill_ids' => [$skill->id],
                'proficiency_levels' => [$skill->id => 'advanced'],
                'skill_years_experience' => [$skill->id => 4],
            ])
            ->assertRedirect(route('professional.profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('professional_skills', [
            'professional_profile_id' => $profile->id,
            'skill_id' => $skill->id,
            'proficiency_level' => 'advanced',
            'years_experience' => 4,
        ]);
    }

    public function test_professional_cannot_make_unverified_draft_profile_public(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = $user->professionalProfile()->create();

        $this->actingAs($user)
            ->from(route('professional.profile.edit'))
            ->patch(route('professional.profile.update'), [
                'availability_status' => ProfessionalAvailabilityStatus::UNKNOWN->value,
                'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            ])
            ->assertRedirect(route('professional.profile.edit'))
            ->assertSessionHasErrors(['visibility']);

        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->fresh()->visibility);
    }

    public function test_verified_active_professional_with_published_service_can_choose_public_visibility(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
        ]);
        $category = Category::factory()->create(['status' => 'active']);
        Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('professional.profile.update'), [
                'availability_status' => ProfessionalAvailabilityStatus::AVAILABLE->value,
                'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            ])
            ->assertRedirect(route('professional.profile'))
            ->assertSessionHas('success');

        $this->assertSame(ProfessionalProfile::VISIBILITY_PUBLIC, $profile->fresh()->visibility);
    }

    public function test_professional_can_upload_view_and_remove_profile_photo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole('professional');
        $user->professionalProfile()->create();

        $this->actingAs($user)
            ->post(route('professional.profile.photo.store'), [
                'avatar' => UploadedFile::fake()->image('avatar.png'),
            ])
            ->assertRedirect(route('professional.profile.edit'))
            ->assertSessionHas('success');

        $path = $user->fresh()->profile()->value('avatar_path');

        $this->assertIsString($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)
            ->get(route('professional.profile.photo'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($user)
            ->delete(route('professional.profile.photo.destroy'))
            ->assertRedirect(route('professional.profile.edit'))
            ->assertSessionHas('success');

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->profile()->value('avatar_path'));
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
        $profile = $user->professionalProfile()->create()->refresh();

        $this->assertSame(ProfessionalProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame(ProfessionalProfile::VISIBILITY_PRIVATE, $profile->visibility);
        $this->assertSame(ProfessionalVerificationStatus::PENDING, $profile->verification_status);
    }
}
