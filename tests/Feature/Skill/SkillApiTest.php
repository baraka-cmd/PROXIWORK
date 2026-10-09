<?php

declare(strict_types=1);

namespace Tests\Feature\Skill;

use App\Enums\SkillStatus;
use App\Models\ProfessionalProfile;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function test_admin_can_update_skill_and_reactivate_an_archived_skill(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $skill = Skill::factory()->create([
            'name' => 'PHP',
            'slug' => 'php',
            'status' => SkillStatus::ARCHIVED,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/skills/'.$skill->id, [
                'name' => 'PHP Laravel',
                'slug' => 'php-laravel',
                'status' => SkillStatus::ACTIVE->value,
                'sort_order' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'PHP Laravel');

        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'name' => 'PHP Laravel',
            'slug' => 'php-laravel',
            'status' => SkillStatus::ACTIVE->value,
            'sort_order' => 10,
        ]);
    }

    public function test_skill_creation_and_update_reject_invalid_payloads(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $skill = Skill::factory()->create(['slug' => 'laravel']);
        Skill::factory()->create(['slug' => 'php']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/skills', [
                'name' => 'L',
                'slug' => 'Laravel Skill',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/skills/'.$skill->id, [
                'slug' => 'php',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_cannot_create_duplicate_slug(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Skill::factory()->create(['slug' => 'laravel']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/skills', [
                'name' => 'Laravel Framework',
                'slug' => 'laravel',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_non_admin_with_skills_view_cannot_manage_skills(): void
    {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator, 'sanctum')
            ->postJson('/api/v1/admin/skills', ['name' => 'Laravel'])
            ->assertForbidden();
    }

    public function test_skill_catalog_requires_no_authentication_but_admin_api_does(): void
    {
        $skill = Skill::factory()->create();

        $this->getJson('/api/v1/skills/'.$skill->id)->assertOk();

        $this->postJson('/api/v1/admin/skills', ['name' => 'Laravel'])
            ->assertUnauthorized();
    }

    public function test_professional_can_only_manage_its_own_skill_associations(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $skill = Skill::factory()->create();
        $profile->skills()->attach($skill->id);

        $otherProfessional = User::factory()->create();
        $otherProfessional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $otherProfessional->id]);

        $response = $this->actingAs($otherProfessional, 'sanctum')
            ->getJson('/api/v1/professional/skills')
            ->assertOk();

        $this->assertSame([], $response->json('data'));

        $this->actingAs($otherProfessional, 'sanctum')
            ->deleteJson('/api/v1/professional/skills/'.$skill->id)
            ->assertUnprocessable();

        $this->assertDatabaseHas('professional_skills', [
            'professional_profile_id' => $profile->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_skill_cannot_be_physically_deleted_while_associated(): void
    {
        $skill = Skill::factory()->create();
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $profile->skills()->attach($skill->id);

        $this->assertDatabaseHas('skills', ['id' => $skill->id]);

        $this->expectException(QueryException::class);
        $skill->delete();
    }

    public function test_professional_skill_relationship_is_unique_at_database_level(): void
    {
        $skill = Skill::factory()->create();
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $profile->skills()->attach($skill->id);

        $this->expectException(UniqueConstraintViolationException::class);
        $profile->skills()->attach($skill->id);
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
