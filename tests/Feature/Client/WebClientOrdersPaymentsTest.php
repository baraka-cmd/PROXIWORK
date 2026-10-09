<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebClientOrdersPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_from_orders(): void
    {
        $this->get('/client/orders')->assertRedirect('/login');
    }

    public function test_non_client_cannot_access_orders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $this->actingAs($user)->get('/client/orders')->assertForbidden();
    }

    public function test_client_can_open_orders_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->get('/client/orders')->assertOk()->assertViewIs('client.orders.index');
    }
}
