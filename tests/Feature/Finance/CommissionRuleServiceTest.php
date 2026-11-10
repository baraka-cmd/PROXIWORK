<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Services\Commission\CommissionRuleService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommissionRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_rate_changes_create_a_new_version_without_rewriting_previous_rules(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $service = app(CommissionRuleService::class);
        $initial = $service->currentRule();

        $changed = $service->changeRate('12.50', $admin, Request::create('/admin/settings', 'PUT'));

        $this->assertNotSame($initial->getKey(), $changed->getKey());
        $this->assertSame('12.50', (string) $changed->rate);
        $this->assertDatabaseHas('commission_rules', ['id' => $initial->getKey(), 'rate' => $initial->rate]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.commission.rule.created']);
    }

    public function test_rate_for_a_past_order_date_uses_the_rule_effective_at_that_time(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $service = app(CommissionRuleService::class);
        $beforeChange = now()->subMinute();
        $service->changeRate('15.00', $admin, Request::create('/admin/settings', 'PUT'));

        $this->assertSame(
            '10.00',
            $service->rateForOrderDate(Carbon::parse($beforeChange)),
        );
        $this->assertSame(
            '15.00',
            $service->rateForOrderDate(now()->addSecond()),
        );
    }
}
