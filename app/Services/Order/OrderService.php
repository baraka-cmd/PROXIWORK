<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Order;
use App\Models\OrderAddressSnapshot;
use App\Models\OrderStatusHistory;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createFromAcceptedQuotation(Quotation $quotation, User $actor): Order
    {
        return DB::transaction(function () use ($quotation, $actor): Order {
            $lockedQuotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->getKey());

            if ($lockedQuotation->status->value !== 'accepted') {
                throw ValidationException::withMessages([
                    'quotation' => 'Une commande ne peut être créée qu’à partir d’un devis accepté.',
                ]);
            }

            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->with(['service', 'address'])
                ->findOrFail($lockedQuotation->service_request_id);

            if ($request->client_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'quotation' => 'Vous ne pouvez pas créer une commande pour ce devis.',
                ]);
            }

            if ($request->status !== ServiceRequestStatus::ACCEPTED) {
                throw ValidationException::withMessages([
                    'service_request' => 'La demande n’est pas dans un état compatible avec la création de commande.',
                ]);
            }

            $acceptedOffer = QuotationOffer::query()
                ->findOrFail($lockedQuotation->accepted_offer_id);

            if ($acceptedOffer->quotation_id !== $lockedQuotation->getKey()) {
                throw ValidationException::withMessages([
                    'quotation' => 'L’offre acceptée n’appartient pas à ce devis.',
                ]);
            }

            $existing = Order::query()
                ->where('accepted_offer_id', $acceptedOffer->getKey())
                ->first();

            if ($existing !== null) {
                return $existing->load(['items', 'addressSnapshot', 'statusHistories']);
            }

            if ($request->address === null) {
                throw ValidationException::withMessages([
                    'address' => 'Une adresse d’exécution est requise pour créer la commande.',
                ]);
            }

            if ($request->address->user_id !== $request->client_id) {
                throw ValidationException::withMessages([
                    'address' => 'L’adresse de la demande n’appartient pas au client.',
                ]);
            }

            if ($request->professional_id === null) {
                throw ValidationException::withMessages([
                    'professional' => 'La commande ne peut pas être créée sans professionnel cible.',
                ]);
            }

            $now = now();

            $order = Order::query()->forceCreate([
                'service_request_id' => $request->getKey(),
                'quotation_id' => $lockedQuotation->getKey(),
                'accepted_offer_id' => $acceptedOffer->getKey(),
                'client_id' => $request->client_id,
                'professional_id' => $request->professional_id,
                'status' => OrderStatus::PENDING_PAYMENT,
                'currency' => strtoupper($acceptedOffer->currency),
                'subtotal' => $acceptedOffer->amount,
                'total' => $acceptedOffer->amount,
                'accepted_at' => $lockedQuotation->accepted_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $order->forceFill([
                'order_number' => sprintf('PW-%s-%06d', $now->format('Y'), $order->getKey()),
            ])->save();

            $order->items()->forceCreate([
                'service_id' => $request->service_id,
                'service_title' => $request->service?->title ?? $request->title,
                'service_description' => $acceptedOffer->description,
                'quantity' => 1,
                'unit_price' => $acceptedOffer->amount,
                'subtotal' => $acceptedOffer->amount,
                'currency' => strtoupper($acceptedOffer->currency),
                'duration_value' => $acceptedOffer->duration_value,
                'duration_unit' => $acceptedOffer->duration_unit->value,
                'conditions' => $acceptedOffer->conditions,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            OrderAddressSnapshot::query()->forceCreate([
                'order_id' => $order->getKey(),
                'recipient_name' => $request->address->recipient_name,
                'contact_phone' => $request->address->contact_phone,
                'country_code' => $request->address->country_code,
                'province' => $request->address->province,
                'city' => $request->address->city,
                'commune' => $request->address->commune,
                'neighborhood' => $request->address->neighborhood,
                'address_line_1' => $request->address->address_line_1,
                'address_line_2' => $request->address->address_line_2,
                'landmark' => $request->address->landmark,
                'postal_code' => $request->address->postal_code,
                'latitude' => $request->address->latitude,
                'longitude' => $request->address->longitude,
                'additional_instructions' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            OrderStatusHistory::query()->forceCreate([
                'order_id' => $order->getKey(),
                'from_status' => null,
                'to_status' => OrderStatus::PENDING_PAYMENT,
                'changed_by' => $actor->getKey(),
                'reason' => 'Order created from accepted quotation offer.',
                'metadata' => ['accepted_offer_id' => $acceptedOffer->getKey()],
                'created_at' => $now,
            ]);

            return $order->refresh()->load([
                'items',
                'addressSnapshot',
                'statusHistories',
                'acceptedOffer',
                'serviceRequest',
            ]);
        }, attempts: 3);
    }
}
