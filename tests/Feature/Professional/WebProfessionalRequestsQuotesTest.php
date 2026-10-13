<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\QuotationDurationUnit;
use App\Enums\ServiceRequestStatus;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
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

    public function test_professional_sees_only_requests_assigned_to_their_profile(): void
    {
        $professional = $this->professional();
        $otherProfessional = $this->professional();
        $client = $this->client();

        $ownRequest = $this->requestFor($professional, $client, 'Installation réseau');
        $otherRequest = $this->requestFor($otherProfessional, $client, 'Réparation ordinateur');

        $this->actingAs($professional)
            ->get(route('professional.requests.index'))
            ->assertOk()
            ->assertSee('Installation réseau')
            ->assertDontSee('Réparation ordinateur');

        $this->actingAs($professional)
            ->get(route('professional.requests.show', $ownRequest))
            ->assertOk()
            ->assertSee('Installation réseau');

        $this->actingAs($professional)
            ->get(route('professional.requests.show', $otherRequest))
            ->assertForbidden();
    }

    public function test_professional_can_reject_only_their_own_request_with_auditable_reason(): void
    {
        $professional = $this->professional();
        $otherProfessional = $this->professional();
        $client = $this->client();
        $ownRequest = $this->requestFor($professional, $client, 'Installation réseau');
        $otherRequest = $this->requestFor($otherProfessional, $client, 'Réparation ordinateur');

        $this->actingAs($professional)
            ->post(route('professional.requests.reject', $ownRequest), [
                'reason' => 'Le périmètre demandé ne correspond pas à mes compétences actuelles.',
            ])
            ->assertRedirect(route('professional.requests.show', $ownRequest));

        $this->assertDatabaseHas('service_requests', [
            'id' => $ownRequest->getKey(),
            'status' => ServiceRequestStatus::REJECTED->value,
        ]);
        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $ownRequest->getKey(),
            'from_status' => ServiceRequestStatus::REQUESTED->value,
            'to_status' => ServiceRequestStatus::REJECTED->value,
            'changed_by' => $professional->getKey(),
            'reason' => 'Le périmètre demandé ne correspond pas à mes compétences actuelles.',
        ]);

        $this->actingAs($professional)
            ->post(route('professional.requests.reject', $otherRequest), [
                'reason' => 'Tentative de modification d’une demande tierce.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('service_requests', [
            'id' => $otherRequest->getKey(),
            'status' => ServiceRequestStatus::REQUESTED->value,
        ]);
    }

    public function test_professional_can_create_one_quote_for_their_own_requested_service(): void
    {
        $professional = $this->professional();
        $client = $this->client();
        $serviceRequest = $this->requestFor($professional, $client, 'Installation réseau');

        $this->actingAs($professional)
            ->post(route('professional.quotes.store', $serviceRequest), [
                'amount' => '125.50',
                'currency' => 'usd',
                'description' => 'Installation et configuration complète du réseau local.',
                'duration_value' => 2,
                'duration_unit' => QuotationDurationUnit::DAYS->value,
                'conditions' => 'Le client garantit l’accès aux locaux.',
                'valid_until' => now()->addDays(5)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->getKey(),
            'status' => ServiceRequestStatus::QUOTED->value,
        ]);

        $this->assertDatabaseHas('quotations', [
            'service_request_id' => $serviceRequest->getKey(),
            'status' => 'sent',
        ]);

        $quotation = $serviceRequest->quotation()->with('currentOffer')->firstOrFail();
        $this->assertSame('USD', $quotation->currentOffer->currency);
        $this->assertSame('125.50', (string) $quotation->currentOffer->amount);
        $this->assertDatabaseHas('quotation_events', [
            'quotation_id' => $quotation->getKey(),
            'type' => 'quote_created',
        ]);

        $this->actingAs($professional)
            ->post(route('professional.quotes.store', $serviceRequest), [
                'amount' => '140.00',
                'currency' => 'USD',
                'description' => 'Une deuxième tentative ne doit pas créer un second devis.',
                'duration_value' => 2,
                'duration_unit' => QuotationDurationUnit::DAYS->value,
                'valid_until' => now()->addDays(5)->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('quotations', 1);
    }

    public function test_professional_cannot_create_or_read_a_quote_for_another_professional_request(): void
    {
        $professional = $this->professional();
        $otherProfessional = $this->professional();
        $client = $this->client();
        $serviceRequest = $this->requestFor($otherProfessional, $client, 'Réparation ordinateur');

        $this->actingAs($professional)
            ->get(route('professional.quotes.create', $serviceRequest))
            ->assertForbidden();

        $this->actingAs($professional)
            ->post(route('professional.quotes.store', $serviceRequest), [
                'amount' => '125.50',
                'currency' => 'USD',
                'description' => 'Installation et configuration complète du réseau local.',
                'duration_value' => 2,
                'duration_unit' => QuotationDurationUnit::DAYS->value,
                'valid_until' => now()->addDays(5)->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();
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

    private function professional(): User
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);

        return $user;
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    private function requestFor(User $professional, User $client, string $title): ServiceRequest
    {
        $profile = $professional->professionalProfile()->firstOrFail();
        $service = Service::factory()->create([
            'professional_profile_id' => $profile->getKey(),
            'title' => $title,
        ]);

        return ServiceRequest::factory()->create([
            'client_id' => $client->getKey(),
            'professional_id' => $profile->getKey(),
            'service_id' => $service->getKey(),
            'title' => $title,
            'status' => ServiceRequestStatus::REQUESTED,
            'requested_at' => now(),
        ]);
    }
}
