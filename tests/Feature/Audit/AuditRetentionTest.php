<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_retention_command_removes_only_expired_audit_logs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $expired = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'expired_action',
        ]);
        $expired->forceFill([
            'created_at' => now()->subDays(181),
            'updated_at' => now()->subDays(181),
        ])->saveQuietly();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'recent_action',
        ]);

        $this->artisan('audit:prune --days=180')->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'expired_action']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recent_action']);
    }
}
