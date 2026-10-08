<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        return $user;
    }

    public function test_admin_can_list_and_open_audit_entries(): void
    {
        $admin = $this->auditor();
        $actor = User::factory()->create();

        $log = AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'admin.support.replied',
            'subject_type' => User::class,
            'subject_id' => $actor->id,
            'ip_address' => '192.168.1.10',
            'user_agent' => 'TestAgent',
            'metadata' => ['message_id' => 12],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('admin.support.replied');

        $this->actingAs($admin)
            ->get(route('admin.audit.show', $log))
            ->assertOk()
            ->assertSee('message_id');
    }

    public function test_non_auditor_cannot_access_audit(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }

    public function test_audit_center_is_read_only(): void
    {
        $admin = $this->auditor();
        $log = AuditLog::query()->create([
            'action' => 'test.action',
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'metadata' => ['safe' => true],
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.audit.show', $log));

        $response->assertStatus(405);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }
}
