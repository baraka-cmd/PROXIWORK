<?php

declare(strict_types=1);

namespace Tests\Feature\Skill;

use App\Enums\SkillStatus;
use App\Models\ProfessionalProfile;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_active_skills_are_publicly_searchable(): void
    {
        Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        Skill::factory()->create(['name' => 'Ancienne', 'slug' => 'ancienne', 'status' => SkillStatus::ARCHIVED]);

        $this->getJson('/api/v1/skills?search=laravel')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Laravel')
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_archived_skill_is_not_publicly_visible(): void
    {
        $skill = Skill::factory()->create(['status' => SkillStatus::ARCHIVED]);

        $this->getJson('/api/v1/skills/'.$skill->id)->assertNotFound();
    }

    public function test_admin_can_create_and_archive_skill(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/skills', [
                'name' => 'Laravel',
            ])
            ->assertCreated();

        $skillId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/admin/skills/'.$skillId)
            ->assertNoContent();

        $this->assertDatabaseHas('skills', [
            'id' => $skillId,
            'status' => SkillStatus::ARCHIVED->value,
        ]);
    }

    public function test_client_cannot_manage_skills(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/admin/skills', ['name' => 'Laravel'])
            ->assertForbidden();
    }

    public function test_professional_can_attach_and_detach_existing_active_skill(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $skill = Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/skills', [
                'skill_id' => $skill->id,
                'proficiency_level' => 'advanced',
                'years_experience' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.skill.id', $skill->id)
            ->assertJsonPath('data.proficiency_level', 'advanced');

        $this->assertDatabaseHas('professional_skills', [
            'professional_profile_id' => $profile->id,
            'skill_id' => $skill->id,
            'years_experience' => 4,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->deleteJson('/api/v1/professional/skills/'.$skill->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('professional_skills', [
            'professional_profile_id' => $profile->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_professional_cannot_attach_archived_skill(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $skill = Skill::factory()->create(['status' => SkillStatus::ARCHIVED]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/skills', ['skill_id' => $skill->id])
            ->assertUnprocessable();
    }

    public function test_duplicate_professional_skill_is_rejected(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $skill = Skill::factory()->create();

        $profile->skills()->attach($skill->id);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/professional/skills', ['skill_id' => $skill->id])
            ->assertUnprocessable();
    }

    public function test_professional_cannot_manage_another_profile_skill(): void
    {
        $professionalA = User::factory()->create();
        $professionalA->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professionalA->id]);

        $professionalB = User::factory()->create();
        $professionalB->assignRole('professional');
        $profileB = ProfessionalProfile::factory()->create(['user_id' => $professionalB->id]);
        $skill = Skill::factory()->create();
        $profileB->skills()->attach($skill->id);

        $this->actingAs($professionalA, 'sanctum')
            ->deleteJson('/api/v1/professional/skills/'.$skill->id)
            ->assertUnprocessable();

        $this->assertDatabaseHas('professional_skills', [
            'professional_profile_id' => $profileB->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_professional_query_is_bounded(): void
    {
        $this->getJson('/api/v1/skills?per_page=101')->assertUnprocessable();
    }
}
