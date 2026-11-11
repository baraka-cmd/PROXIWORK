<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TechnicalMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_can_view_technical_monitoring_without_sensitive_payloads(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Supervision technique')
            ->assertSee('Files et tâches échouées')
            ->assertSee('Notifications et livraisons')
            ->assertSee('Services de paiement')
            ->assertSee('Stockage et fichiers')
            ->assertSee('Erreurs applicatives')
            ->assertSee('Performance et disponibilité')
            ->assertDontSee('APP_KEY')
            ->assertDontSee('AWS_SECRET_ACCESS_KEY');
    }

    public function test_aggregated_incident_is_visible_without_exception_message_or_trace(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        DB::table('application_incidents')->insert([
            'fingerprint' => hash('sha256', 'test-incident'),
            'exception_type' => 'RuntimeException',
            'location_hint' => 'OrderService.php:120',
            'occurrence_count' => 3,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Incidents applicatifs agrégés')
            ->assertSee('RuntimeException')
            ->assertSee('OrderService.php:120')
            ->assertSee('3');
    }

    public function test_recent_worker_heartbeat_is_counted_as_active(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        DB::table('queue_worker_heartbeats')->insert([
            'worker_id' => hash('sha256', 'test-worker'),
            'hostname' => 'test-worker',
            'process_id' => 1234,
            'last_seen_at' => now()->subSeconds(10),
            'created_at' => now()->subSeconds(10),
            'updated_at' => now()->subSeconds(10),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Workers actifs (2 min)')
            ->assertSee('Heartbeat récent');
    }

    public function test_failed_job_payload_and_exception_trace_are_never_rendered(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\SensitiveJob',
                'data' => ['command' => 'PRIVATE_JOB_PAYLOAD_SECRET'],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: PRIVATE_EXCEPTION_TRACE_SECRET',
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('SensitiveJob')
            ->assertDontSee('PRIVATE_JOB_PAYLOAD_SECRET')
            ->assertDontSee('PRIVATE_EXCEPTION_TRACE_SECRET');
    }

    public function test_user_without_monitoring_permission_cannot_view_technical_monitoring(): void
    {
        $this->seed(RbacSeeder::class);

        $support = User::factory()->create();
        $support->assignRole('support');

        $this->actingAs($support)
            ->get(route('admin.monitoring.index'))
            ->assertForbidden();
    }
}
