<?php

declare(strict_types=1);

namespace Tests\Feature\Disputes;

use App\Enums\OrderStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_open_a_dispute_only_for_an_owned_eligible_order(): void
    {
        [$client, , $order] = $this->completedOrderScenario();

        $this->actingAs($client)
            ->post(route('client.orders.disputes.store', $order), [
                'reason_code' => 'quality_dispute',
                'description' => 'La qualité du travail livré ne correspond pas aux conditions acceptées.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_disputes', [
            'order_id' => $order->id,
            'opened_by' => $client->id,
            'client_id' => $client->id,
            'status' => 'open',
        ]);
    }

    public function test_unrelated_client_cannot_open_a_dispute_for_another_order(): void
    {
        [, , $order] = $this->completedOrderScenario();
        $other = User::factory()->create();
        $other->assignRole('client');

        $this->actingAs($other)
            ->post(route('client.orders.disputes.store', $order), [
                'reason_code' => 'quality_dispute',
                'description' => 'Je tente de signaler une commande qui ne m’appartient pas.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('order_disputes', 0);
    }

    public function test_second_dispute_for_same_order_is_rejected(): void
    {
        [$client, , $order] = $this->completedOrderScenario();
        OrderDispute::query()->create([
            'order_id' => $order->id,
            'opened_by' => $client->id,
            'client_id' => $client->id,
            'professional_id' => $order->professional_id,
            'reason_code' => 'quality_dispute',
            'description' => 'Dossier initial déjà enregistré pour cette commande.',
            'status' => 'resolved',
            'priority' => 'normal',
        ]);

        $this->actingAs($client)
            ->from(route('client.orders.show', $order))
            ->post(route('client.orders.disputes.store', $order), [
                'reason_code' => 'other',
                'description' => 'Une deuxième ouverture ne doit pas créer un dossier concurrent.',
            ])
            ->assertSessionHasErrors('order');

        $this->assertDatabaseCount('order_disputes', 1);
    }

    public function test_moderator_can_review_and_move_dispute_to_decision_pending(): void
    {
        [$client, , $order] = $this->completedOrderScenario();
        $dispute = OrderDispute::query()->create([
            'order_id' => $order->id,
            'opened_by' => $client->id,
            'client_id' => $client->id,
            'professional_id' => $order->professional_id,
            'reason_code' => 'quality_dispute',
            'description' => 'La prestation ne correspond pas au résultat convenu dans le devis.',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator)
            ->post(route('admin.disputes.transition', $dispute), [
                'status' => 'under_review',
                'reason' => 'Le dossier est attribué et les éléments initiaux vont être examinés.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_disputes', [
            'id' => $dispute->id,
            'status' => 'under_review',
        ]);

        $this->actingAs($moderator)
            ->post(route('admin.disputes.transition', $dispute), [
                'status' => 'decision_pending',
                'reason' => 'Les explications des deux parties ont été recueillies et vérifiées.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_disputes', [
            'id' => $dispute->id,
            'status' => 'decision_pending',
        ]);
    }

    public function test_moderator_cannot_record_final_decision_without_decision_permission(): void
    {
        [$client, , $order] = $this->completedOrderScenario();
        $dispute = OrderDispute::query()->create([
            'order_id' => $order->id,
            'opened_by' => $client->id,
            'client_id' => $client->id,
            'professional_id' => $order->professional_id,
            'reason_code' => 'quality_dispute',
            'description' => 'La prestation ne correspond pas au résultat convenu dans le devis.',
            'status' => 'decision_pending',
            'priority' => 'normal',
        ]);
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator)
            ->post(route('admin.disputes.decide', $dispute), [
                'resolution_code' => 'no_breach',
                'resolution_note' => 'Après examen, aucun manquement contractuel n’a été établi.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('order_disputes', [
            'id' => $dispute->id,
            'status' => 'decision_pending',
            'resolution_code' => null,
        ]);
    }

    /** @return array{0: User, 1: ProfessionalProfile, 2: Order} */
    private function completedOrderScenario(): array
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);
        $client = User::factory()->create();
        $client->assignRole('client');
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'category_id' => Category::factory()->create()->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        $address = Address::factory()->default()->create(['user_id' => $client->id]);
        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::QUOTED,
            'requested_at' => now(),
        ]);
        $quotation = Quotation::query()->forceCreate([
            'service_request_id' => $request->id,
            'status' => 'sent',
        ]);
        $offer = $quotation->offers()->forceCreate([
            'created_by' => $professionalUser->id,
            'actor_type' => 'professional',
            'version' => 1,
            'amount' => 500,
            'currency' => 'USD',
            'description' => 'Service professionnel.',
            'duration_value' => 10,
            'duration_unit' => 'days',
            'conditions' => 'Conditions.',
            'valid_until' => now()->addDays(5),
        ]);
        $quotation->forceFill([
            'status' => 'accepted',
            'current_offer_id' => $offer->id,
            'accepted_offer_id' => $offer->id,
            'accepted_at' => now(),
        ])->save();
        $request->forceFill(['status' => ServiceRequestStatus::ACCEPTED])->save();
        $order = app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client);
        $order->forceFill(['status' => OrderStatus::COMPLETED, 'completed_at' => now()])->save();

        return [$client, $professional, $order->fresh()];
    }
}
