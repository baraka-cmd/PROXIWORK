<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Models\ProfessionalProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAdminProfessionalsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::where('name','admin')->firstOrFail());
        return $user;
    }

    public function test_admin_can_list_and_view_professionals(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($admin)->get(route('admin.professionals.index'))->assertOk()->assertSee($professional->user->name);
        $this->actingAs($admin)->get(route('admin.professionals.show',$professional))->assertOk();
    }

    public function test_client_cannot_view_professional_administration(): void
    {
        $this->seed(RbacSeeder::class);
        $client = User::factory()->create();
        $client->assignRole(Role::where('name','client')->firstOrFail());
        $professional = ProfessionalProfile::factory()->create();

        $this->actingAs($client)->get(route('admin.professionals.index'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.professionals.show',$professional))->assertForbidden();
    }

    public function test_admin_can_run_verification_workflow(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create(['verification_status'=>ProfessionalVerificationStatus::PENDING]);

        $this->actingAs($admin)->post(route('admin.professionals.verification.start',$professional),['note'=>'Dossier reçu'])->assertRedirect();
        $this->assertSame(ProfessionalVerificationStatus::UNDER_REVIEW,$professional->refresh()->verification_status);

        $this->actingAs($admin)->post(route('admin.professionals.verification.verify',$professional),['note'=>'Contrôle terminé'])->assertRedirect();
        $this->assertSame(ProfessionalVerificationStatus::VERIFIED,$professional->refresh()->verification_status);
    }

    public function test_admin_can_suspend_and_activate_professional(): void
    {
        $admin = $this->admin();
        $professional = ProfessionalProfile::factory()->create(['verification_status'=>ProfessionalVerificationStatus::VERIFIED]);

        $this->actingAs($admin)->post(route('admin.professionals.suspend',$professional))->assertRedirect();
        $this->assertSame(UserAccountStatus::SUSPENDED,$professional->user->fresh()->account_status);

        $this->actingAs($admin)->post(route('admin.professionals.activate',$professional))->assertRedirect();
        $this->assertSame(UserAccountStatus::ACTIVE,$professional->user->fresh()->account_status);
    }
}
