<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Professional;

use App\Enums\UserAccountStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProfessionalManagementTest extends TestCase
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

    public function test_admin_can_list_and_filter_professionals(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create([
            'professional_title' => 'Développeur Laravel',
            'verification_status' => 'verified',
        ]);
        $professional->user->update(['account_status' => UserAccountStatus::SUSPENDED->value]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/professionals?search=Laravel&account_status=suspended&verification_status=verified')
            ->assertOk()
            ->assertJsonPath('data.0.id', $professional->id)
            ->assertJsonPath('data.0.account.account_status', 'suspended')
            ->assertJsonPath('data.0.verification_status', 'verified');
    }

    public function test_admin_can_view_professional_dossier(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create([
            'professional_title' => 'Développeur',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/professionals/{$professional->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $professional->id)
            ->assertJsonPath('data.account.id', $professional->user_id)
            ->assertJsonPath('data.professional_title', 'Développeur')
            ->assertJsonStructure(['data' => ['skills', 'verification_history', 'counts']]);
    }

    public function test_admin_can_suspend_and_activate_professional_without_changing_verification_status(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create([
            'verification_status' => 'verified',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.account.account_status', 'suspended')
            ->assertJsonPath('data.verification_status', 'verified');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.account.account_status', 'active')
            ->assertJsonPath('data.verification_status', 'verified');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.professional.suspended',
            'subject_id' => $professional->id,
        ]);
    }

    public function test_admin_cannot_suspend_own_professional_account(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/professionals/{$professional->id}/suspend")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['professional']);
    }

    public function test_non_admin_cannot_manage_professionals(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/professionals')
            ->assertForbidden();

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/admin/professionals/{$professional->id}")
            ->assertForbidden();
    }
}
