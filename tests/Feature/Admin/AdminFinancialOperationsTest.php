<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinancialOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_financial_admin_can_open_refunds_movements_and_reports(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.finance.refunds'))
            ->assertOk()->assertViewIs('admin.refunds.index');

        $this->actingAs($admin)->get(route('admin.finance.movements'))
            ->assertOk()->assertViewIs('admin.finance.movements.index');

        $this->actingAs($admin)->get(route('admin.finance.reports'))
            ->assertOk()->assertViewIs('admin.finance.reports.index');

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.finance.report.viewed', 'user_id' => $admin->id]);
    }

    public function test_client_cannot_open_administrative_financial_operations(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get(route('admin.finance.refunds'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.finance.movements'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.finance.reports'))->assertForbidden();
    }

    public function test_financial_report_export_is_streamed_as_private_csv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.finance.reports.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
