<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentConflictException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Payments\DTO\PaymentRequest;
use App\Services\Commission\CommissionService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly PaymentTransactionService $transactionService,
        private readonly DatabaseManager $database,
        private readonly CommissionService $commissionService,
    ) {
    }

    public function initiate(
        Order $order,
        User $client,
        PaymentMethod $method,
        PaymentProvider $provider,
        string $idempotencyKey,
    ): Payment {
        $prepared = $this->prepareIntent(
            $order,
            $client,
            $method,
            $provider,
            $idempotencyKey,
        );

        $intent = $prepared['intent'];
        $payment = $this->ensurePayment($order, $client, $intent);

        if ($prepared['created'] === false && $intent->request_fingerprint !== $prepared['fingerprint']) {
            throw new PaymentConflictException(
                'La même clé d’idempotence a déjà été utilisée avec une autre opération.'
            );
        }

        if ($prepared['created'] === false && $intent->status->isFinal()) {
            return $payment->load('transactions');
        }

        if ($payment->status === PaymentStatus::SUCCEEDED) {
            return $payment->load('transactions');
        }

        $transaction = $this->transactionService->ensure(
            payment: $payment,
            idempotencyKey: $idempotencyKey,
            amount: (string) $payment->amount,
            currency: $payment->currency,
            provider: $provider->value,
        );

        try {
            $result = $this->gatewayManager
                ->gateway($provider)
                ->initiate(new PaymentRequest(
                    orderId: $order->getKey(),
                    idempotencyKey: $idempotencyKey,
                    amount: (string) $payment->amount,
                    currency: $payment->currency,
                    method: $method,
                    provider: $provider,
                    metadata: ['payment_intent_id' => $intent->getKey(), 'payment_id' => $payment->getKey()],
                ));
        } catch (Throwable $exception) {
            // The local transaction remains durable. The same key must be reused
            // to reconcile an unknown provider outcome safely.
            throw new PaymentGatewayUnavailableException($exception);
        }

        $transaction = $this->transactionService->applyResult($transaction, $result);

        return $this->database->transaction(function () use ($payment, $intent, $transaction, $result): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $lockedIntent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            if ($lockedPayment->status === PaymentStatus::SUCCEEDED) {
                return $lockedPayment->load('transactions');
            }

            $lockedIntent->forceFill([
                'status' => $result->status,
                'provider_reference' => $result->providerReference,
                'redirect_url' => $result->redirectUrl,
                'instructions' => $result->instructions,
                'metadata' => $result->metadata,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
            ])->save();

            $lockedPayment->forceFill([
                'status' => $result->status,
                'paid_at' => $result->status === PaymentStatus::SUCCEEDED ? now() : $lockedPayment->paid_at,
                'failed_at' => $result->status === PaymentStatus::FAILED ? now() : $lockedPayment->failed_at,
                'cancelled_at' => $result->status === PaymentStatus::CANCELLED ? now() : $lockedPayment->cancelled_at,
            ])->save();

            if ($result->status === PaymentStatus::SUCCEEDED) {
                $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);

                if ($order->status === OrderStatus::PENDING_PAYMENT) {
                    $from = $order->status;
                    $order->forceFill([
                        'status' => OrderStatus::CONFIRMED,
                        'confirmed_at' => now(),
                    ])->save();

                    OrderStatusHistory::query()->forceCreate([
                        'order_id' => $order->getKey(),
                        'from_status' => $from,
                        'to_status' => OrderStatus::CONFIRMED,
                        'changed_by' => $lockedPayment->client_id,
                        'reason' => 'payment_succeeded',
                        'metadata' => [
                            'payment_id' => $lockedPayment->getKey(),
                            'payment_transaction_id' => $transaction->getKey(),
                        ],
                    ]);
                } elseif ($order->status !== OrderStatus::CONFIRMED) {
                    throw new PaymentConflictException(
                        'Le paiement est confirmé mais la commande est dans un état incompatible.'
                    );
                }
            }

            if ($result->status === PaymentStatus::SUCCEEDED) {
                $this->commissionService->postForPayment($lockedPayment, $transaction);
            }

            return $lockedPayment->refresh()->load('transactions');
        }, attempts: 3);
    }

    public function paymentForOrder(Order $order, User $client): Payment
    {
        $payment = Payment::query()
            ->where('order_id', $order->getKey())
            ->where('client_id', $client->getKey())
            ->with('transactions')
            ->first();

        if ($payment === null) {
            throw new PaymentConflictException('Aucun paiement n’est associé à cette commande.');
        }

        return $payment;
    }

    /**
     * @return array{created: bool, intent: PaymentIntent, fingerprint: string}
     */
    private function prepareIntent(
        Order $order,
        User $client,
        PaymentMethod $method,
        PaymentProvider $provider,
        string $idempotencyKey,
    ): array {
        return $this->database->transaction(function () use ($order, $client, $method, $provider, $idempotencyKey): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->client_id !== $client->getKey()) {
                throw new PaymentConflictException('Vous ne pouvez pas payer cette commande.');
            }

            if (in_array($lockedOrder->status, [OrderStatus::PENDING_PAYMENT, OrderStatus::CONFIRMED], true) === false) {
                throw new PaymentConflictException('Cette commande n’accepte plus de paiement.');
            }

            $fingerprint = hash('sha256', implode('|', [
                $lockedOrder->getKey(),
                $lockedOrder->total,
                strtoupper($lockedOrder->currency),
                $method->value,
                $provider->value,
            ]));

            $existing = PaymentIntent::query()
                ->where('client_id', $client->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return [
                    'created' => false,
                    'intent' => $existing,
                    'fingerprint' => $fingerprint,
                ];
            }

            if ($lockedOrder->status !== OrderStatus::PENDING_PAYMENT) {
                throw new PaymentConflictException('Cette commande est déjà payée ou en cours de traitement.');
            }

            $active = PaymentIntent::query()
                ->where('order_id', $lockedOrder->getKey())
                ->whereIn('status', [
                    PaymentStatus::INITIATED->value,
                    PaymentStatus::PENDING->value,
                    PaymentStatus::PROCESSING->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                throw new PaymentConflictException(
                    'Un paiement est déjà en cours pour cette commande. Réutilisez la même clé d’idempotence.'
                );
            }

            $intent = PaymentIntent::query()->forceCreate([
                'order_id' => $lockedOrder->getKey(),
                'client_id' => $client->getKey(),
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'method' => $method,
                'provider' => $provider,
                'status' => PaymentStatus::INITIATED,
                'currency' => strtoupper($lockedOrder->currency),
                'amount' => $lockedOrder->total,
            ]);

            return [
                'created' => true,
                'intent' => $intent,
                'fingerprint' => $fingerprint,
            ];
        }, attempts: 3);
    }

    private function ensurePayment(Order $order, User $client, PaymentIntent $intent): Payment
    {
        return $this->database->transaction(function () use ($order, $client, $intent): Payment {
            $payment = Payment::query()
                ->where('order_id', $order->getKey())
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return Payment::query()->forceCreate([
                    'order_id' => $order->getKey(),
                    'client_id' => $client->getKey(),
                    'payment_intent_id' => $intent->getKey(),
                    'method' => $intent->method,
                    'provider' => $intent->provider,
                    'status' => $intent->status,
                    'currency' => strtoupper($intent->currency),
                    'amount' => $intent->amount,
                ]);
            }

            $payment->forceFill([
                'payment_intent_id' => $intent->getKey(),
                'method' => $intent->method,
                'provider' => $intent->provider,
            ])->save();

            return $payment->refresh();
        }, attempts: 3);
    }
}
