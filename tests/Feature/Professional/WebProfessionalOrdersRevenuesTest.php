<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProfessionalOrdersRevenuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_open_orders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('professional.orders.index'));

        $response->assertOk();
        $response->assertSee('Mes commandes');
    }

    public function test_professional_can_open_revenues(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('professional.revenues.index'));

        $response->assertOk();
        $response->assertSee('Mes revenus');
    }

    public function test_client_cannot_open_professional_financial_area(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->get(route('professional.orders.index'))->assertForbidden();
        $this->actingAs($user)->get(route('professional.revenues.index'))->assertForbidden();
    }
}
