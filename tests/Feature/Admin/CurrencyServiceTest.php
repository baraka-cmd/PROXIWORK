<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\User;
use App\Services\Settings\CurrencyService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_currency_can_be_set_as_default_and_an_unused_currency_can_be_deactivated(): void
    {
        $admin = $this->admin();
        $service = app(CurrencyService::class);
        $request = Request::create('/admin/settings/currencies/CDF', 'PATCH');

        $service->update('CDF', true, true, $admin, $request);
        $service->update('USD', false, false, $admin, $request);

        $this->assertSame('CDF', $service->defaultCode());
        $this->assertFalse($service->isActive('USD'));
        $this->assertTrue($service->isActive('CDF'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.settings.currency.updated']);
    }

    public function test_currency_cannot_be_deactivated_while_a_published_service_uses_it(): void
    {
        $admin = $this->admin();
        Service::factory()->create([
            'currency' => 'USD',
            'status' => 'published',
        ]);

        $service = app(CurrencyService::class);
        $request = Request::create('/admin/settings/currencies', 'PATCH');
        $service->update('CDF', true, true, $admin, $request);

        $this->expectException(ValidationException::class);
        $service->update('USD', false, false, $admin, $request);
    }

    public function test_default_currency_cannot_be_deactivated(): void
    {
        $admin = $this->admin();

        $this->expectException(ValidationException::class);

        app(CurrencyService::class)->update(
            'USD',
            false,
            false,
            $admin,
            Request::create('/admin/settings/currencies/USD', 'PATCH'),
        );
    }

    public function test_inactive_currency_is_not_accepted_as_a_new_platform_currency(): void
    {
        $service = app(CurrencyService::class);

        $this->assertTrue($service->isActive('USD'));
        $this->assertTrue($service->isActive('CDF'));
        $this->assertFalse($service->isActive('EUR'));
        $this->assertSame('USD', $service->defaultCode());
    }
}
