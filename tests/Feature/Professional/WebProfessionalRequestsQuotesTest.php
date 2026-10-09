<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProfessionalRequestsQuotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_open_professional_requests_or_quotes(): void
    {
        $this->get('/professional/requests')->assertRedirect('/login');
        $this->get('/professional/quotes')->assertRedirect('/login');
    }

    public function test_client_cannot_open_professional_requests_or_quotes(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get('/professional/requests')->assertForbidden();
        $this->actingAs($client)->get('/professional/quotes')->assertForbidden();
    }

    public function test_professional_can_open_empty_requests_and_quotes_pages(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)
            ->get('/professional/requests')
            ->assertOk()
            ->assertViewIs('professional.requests.index')
            ->assertSee('Demandes de service')
            ->assertSee('Aucune demande pour le moment');

        $this->actingAs($professional)
            ->get('/professional/quotes')
            ->assertOk()
            ->assertViewIs('professional.quotes.index')
            ->assertSee('Mes devis')
            ->assertSee('Aucun devis envoyé');
    }
}
