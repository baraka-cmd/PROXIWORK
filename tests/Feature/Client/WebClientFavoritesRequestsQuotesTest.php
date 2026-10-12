<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebClientFavoritesRequestsQuotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_open_favorites_requests_and_quotes_pages(): void
    {
        $client = $this->client();

        $this->actingAs($client)->get('/client/favorites')->assertOk()->assertViewIs('client.favorites.index');
        $this->actingAs($client)->get('/client/requests')->assertOk()->assertViewIs('client.requests.index');
        $this->actingAs($client)->get('/client/quotes')->assertOk()->assertViewIs('client.quotes.index');
    }

    public function test_guest_is_redirected_to_login_for_all_three_areas(): void
    {
        $this->get('/client/favorites')->assertRedirect('/login');
        $this->get('/client/requests')->assertRedirect('/login');
        $this->get('/client/quotes')->assertRedirect('/login');
    }

    public function test_non_client_cannot_open_client_modules(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)->get('/client/favorites')->assertForbidden();
        $this->actingAs($professional)->get('/client/requests')->assertForbidden();
        $this->actingAs($professional)->get('/client/quotes')->assertForbidden();
    }

    public function test_favorites_page_only_exposes_authenticated_clients_favorites(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);

        Favorite::create(['user_id' => $client->id, 'professional_profile_id' => $professional->id]);

        $response = $this->actingAs($client)->get('/client/favorites')->assertOk();
        $response->assertSee($professionalUser->name);

        Favorite::create(['user_id' => $otherClient->id, 'professional_profile_id' => $professional->id]);

        $this->actingAs($client)->get('/client/favorites')->assertOk();
    }

    public function test_client_can_add_and_remove_a_favorite_through_web(): void
    {
        $client = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->actingAs($client)
            ->put('/client/favorites/'.$professional->id)
            ->assertRedirect();

        $favorite = Favorite::query()->where('user_id', $client->id)->firstOrFail();

        $this->actingAs($client)
            ->delete('/client/favorites/'.$favorite->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_client_request_detail_is_scoped_by_policy(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $serviceRequest = ServiceRequest::factory()->create(['client_id' => $owner->id]);

        $this->actingAs($owner)->get('/client/requests/'.$serviceRequest->id)->assertOk();
        $this->actingAs($other)->get('/client/requests/'.$serviceRequest->id)->assertForbidden();
    }

    public function test_quote_detail_is_scoped_by_policy(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $serviceRequest = ServiceRequest::factory()->create(['client_id' => $owner->id]);
        $quotation = Quotation::query()->forceCreate(['service_request_id' => $serviceRequest->id, 'status' => 'sent']);

        $this->actingAs($owner)->get('/client/quotes/'.$quotation->id)->assertOk();
        $this->actingAs($other)->get('/client/quotes/'.$quotation->id)->assertForbidden();
    }

    public function test_client_can_create_and_edit_a_draft_request(): void
    {
        $client = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($client)
            ->get('/client/requests/create?service='.$service->id)
            ->assertOk()
            ->assertViewIs('client.requests.create');

        $response = $this->actingAs($client)->post('/client/requests', [
            'service_id' => $service->id,
            'title' => 'Créer une application de gestion',
            'description' => 'Je souhaite une application complète pour gérer les activités de mon entreprise.',
            'currency' => 'USD',
        ])->assertRedirect();

        $request = ServiceRequest::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
        $this->assertSame('draft', $request->status->value);

        $this->actingAs($client)
            ->get('/client/requests/'.$request->id.'/edit')
            ->assertOk()
            ->assertViewIs('client.requests.edit');

        $this->actingAs($client)
            ->put('/client/requests/'.$request->id, [
                'title' => 'Application de gestion professionnelle',
                'description' => 'Je souhaite une application complète avec tableau de bord et gestion des utilisateurs.',
                'currency' => 'USD',
            ])
            ->assertRedirect('/client/requests/'.$request->id);

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'title' => 'Application de gestion professionnelle',
            'status' => 'draft',
        ]);
    }

    public function test_client_cannot_edit_another_clients_request(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $owner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($other)->get('/client/requests/'.$serviceRequest->id.'/edit')->assertForbidden();
        $this->actingAs($other)->put('/client/requests/'.$serviceRequest->id, [
            'title' => 'Tentative non autorisée',
            'description' => 'Cette modification ne doit jamais être appliquée au compte propriétaire.',
            'currency' => 'USD',
        ])->assertForbidden();
    }

    public function test_expired_current_offer_cannot_be_accepted(): void
    {
        $client = $this->client();
        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'status' => 'quoted',
        ]);
        $quotation = Quotation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'status' => 'sent',
        ]);
        $offer = QuotationOffer::factory()->create([
            'quotation_id' => $quotation->id,
            'valid_until' => now()->subMinute(),
        ]);
        $quotation->forceFill(['current_offer_id' => $offer->id])->save();

        $this->actingAs($client)
            ->post('/client/quotes/'.$quotation->id.'/accept')
            ->assertSessionHasErrors('valid_until');
    }

    public function test_client_requests_dashboard_filters_lists_and_counts_only_the_current_client(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'title' => 'Demande en attente',
            'status' => 'requested',
            'requested_at' => now(),
        ]);
        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'title' => 'Devis à consulter',
            'status' => 'quoted',
            'requested_at' => now(),
        ]);
        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'title' => 'Demande annulée',
            'status' => 'cancelled',
        ]);
        ServiceRequest::factory()->create([
            'client_id' => $otherClient->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'title' => 'Demande privée autre client',
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($client)
            ->get('/client/requests?status=quoted&search=Devis')
            ->assertOk()
            ->assertViewIs('client.requests.index')
            ->assertSee('Devis à consulter')
            ->assertDontSee('Demande en attente')
            ->assertDontSee('Demande privée autre client');

        $response->assertViewHas('stats', function (array $stats): bool {
            return $stats['total'] === 3
                && $stats['awaiting_professional'] === 1
                && $stats['awaiting_client'] === 1
                && $stats['closed'] === 1;
        });
    }

    public function test_web_submission_records_audit_history_and_notifies_the_recipient(): void
    {
        Notification::fake();

        $client = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($client)->post('/client/requests', [
            'service_id' => $service->id,
            'title' => 'Demande depuis le web',
            'description' => 'Je souhaite une prestation avec un périmètre et un résultat clairement définis.',
            'currency' => 'USD',
        ])->assertRedirect();

        $serviceRequest = ServiceRequest::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($client)
            ->post('/client/requests/'.$serviceRequest->id.'/submit')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $serviceRequest->id,
            'from_status' => 'draft',
            'to_status' => 'requested',
            'changed_by' => $client->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_request_submitted',
            'subject_id' => $serviceRequest->id,
            'user_id' => $client->id,
        ]);

        Notification::assertSentTo($professionalUser, AccountActivityNotification::class);
    }

    public function test_web_client_quote_rejection_updates_request_history_audit_and_notification(): void
    {
        Notification::fake();

        $client = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'status' => 'quoted',
        ]);
        $quotation = Quotation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'status' => 'sent',
        ]);
        $offer = QuotationOffer::factory()->create([
            'quotation_id' => $quotation->id,
            'valid_until' => now()->addDay(),
        ]);
        $quotation->forceFill(['current_offer_id' => $offer->id])->save();

        $this->actingAs($client)
            ->post('/client/quotes/'.$quotation->id.'/reject')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $serviceRequest->id,
            'from_status' => 'quoted',
            'to_status' => 'rejected',
            'changed_by' => $client->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'quotation_rejected',
            'subject_id' => $quotation->id,
            'user_id' => $client->id,
        ]);

        Notification::assertSentTo($professionalUser, AccountActivityNotification::class);
    }

    public function test_quote_page_guides_client_to_add_an_address_before_acceptance(): void
    {
        $client = $this->client();
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'status' => 'quoted',
            'address_id' => null,
        ]);
        $quotation = Quotation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'status' => 'sent',
        ]);
        $offer = QuotationOffer::factory()->create([
            'quotation_id' => $quotation->id,
            'valid_until' => now()->addDay(),
        ]);
        $quotation->forceFill(['current_offer_id' => $offer->id])->save();

        $this->actingAs($client)
            ->get('/client/quotes/'.$quotation->id)
            ->assertOk()
            ->assertSee('Une adresse d’exécution est nécessaire avant l’acceptation.')
            ->assertSee('Choisir l’adresse de cette demande')
            ->assertSee('Ajoutez une adresse avant d’accepter')
            ->assertSee('disabled aria-disabled="true"', false);
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
