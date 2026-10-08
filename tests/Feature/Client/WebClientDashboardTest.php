<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_view_dashboard(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertViewIs('client.dashboard')
            ->assertSeeText('Tableau de bord')
            ->assertSeeText($client->name);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('client.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_professional_cannot_view_client_dashboard(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)
            ->get(route('client.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_uses_authenticated_client_data(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Address::factory()->create([
            'user_id' => $client->id,
            'label' => 'Chez moi',
            'is_default' => true,
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSeeText('Chez moi')
            ->assertSeeText('Adresses enregistrées');
    }
}
