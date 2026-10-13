<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
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

    public function test_professional_orders_are_scoped_to_their_own_work_and_detail(): void
    {
        $client = $this->client();
        $professional = $this->professional();
        $otherProfessional = $this->professional();

        $ownOrder = $this->makeOrder($client, $professional, 'Installation réseau');
        $otherOrder = $this->makeOrder($client, $otherProfessional, 'Réparation ordinateur');

        $this->actingAs($professional)
            ->get(route('professional.orders.index'))
            ->assertOk()
            ->assertSee('#'.$ownOrder->getKey())
            ->assertDontSee('#'.$otherOrder->getKey());

        $this->actingAs($professional)
            ->get(route('professional.orders.show', $ownOrder))
            ->assertOk()
            ->assertSee('Installation réseau');

        $this->actingAs($professional)
            ->get(route('professional.orders.show', $otherOrder))
            ->assertForbidden();
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

    private function makeOrder(User $client, User $professionalUser, string $title): Order
    {
        $professional = $professionalUser->professionalProfile()->firstOrFail();
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->getKey(),
            'title' => $title,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $address = Address::factory()->create(['user_id' => $client->getKey()]);
        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $client->getKey(),
            'professional_id' => $professional->getKey(),
            'service_id' => $service->getKey(),
            'address_id' => $address->getKey(),
            'status' => ServiceRequestStatus::ACCEPTED,
        ]);
        $quotation = Quotation::factory()->create([
            'service_request_id' => $serviceRequest->getKey(),
            'status' => QuotationStatus::ACCEPTED,
            'accepted_at' => now(),
        ]);
        $offer = QuotationOffer::factory()->create([
            'quotation_id' => $quotation->getKey(),
            'created_by' => $professionalUser->getKey(),
            'actor_type' => QuotationOfferActor::PROFESSIONAL->value,
            'amount' => 150,
            'currency' => 'USD',
            'description' => 'Prestation convenue et conservée dans la commande.',
            'conditions' => 'Conditions particulières convenues.',
        ]);
        $quotation->forceFill([
            'current_offer_id' => $offer->getKey(),
            'accepted_offer_id' => $offer->getKey(),
        ])->save();

        return app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client);
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
}
