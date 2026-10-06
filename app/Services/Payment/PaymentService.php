<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentConflictException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Payments\DTO\PaymentRequest;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly DatabaseManager $database,
    ) {}

    public function initiate(
        Order $order,
        User $client,
        PaymentMethod $method,
        PaymentProvider $provider,
        string $idempotencyKey,
    ): PaymentIntent {
        $prepared = $this->prepareIntent(
            $order,
            $client,
            $method,
            $provider,
            $idempotencyKey,
        );

        if (! $prepared['created']) {
            $intent = $prepared['intent'];

            if ($intent->request_fingerprint !== $prepared['fingerprint']) {
                throw new PaymentConflictException(
                    'La même clé d’idempotence a déjà été utilisée avec une autre opération.'
                );
            }

            if (in_array($intent->status, [
                PaymentStatus::SUCCEEDED,
                PaymentStatus::FAILED,
                PaymentStatus::CANCELLED,
                PaymentStatus::EXPIRED,
                PaymentStatus::REFUNDED,
            ], true)) {
                return $intent;
            }
        } else {
            $intent = $prepared['intent'];
        }

        try {
            $result = $this->gatewayManager
                ->gateway($provider)
                ->initiate(new PaymentRequest(
                    orderId: $order->getKey(),
                    idempotencyKey: $idempotencyKey,
                    amount: $intent->amount,
                    currency: $intent->currency,
                    method: $method,
                    provider: $provider,
                    metadata: ['payment_intent_id' => $intent->getKey()],
                ));
        } catch (Throwable $exception) {
            // The intent deliberately remains initiated. A retry with the same
            // idempotency key can safely reconcile an unknown provider outcome.
            throw new PaymentGatewayUnavailableException($exception);
        }

        $expectedAmount = $this->canonicalMoney((string) $intent->amount);
        $returnedAmount = $this->canonicalMoney($result->amount);

        if ($returnedAmount !== $expectedAmount
            || strtoupper($result->currency) !== strtoupper($intent->currency)) {
            $this->markFailed(
                $intent,
                'PROVIDER_AMOUNT_MISMATCH',
                'La réponse du fournisseur ne correspond pas au montant ou à la devise attendus.'
            );

            throw ValidationException::withMessages([
                'payment' => 'La réponse du fournisseur de paiement est incohérente.',
            ]);
        }

        return DB::transaction(function () use ($intent, $result): PaymentIntent {
            $locked = PaymentIntent::query()
                ->lockForUpdate()
                ->findOrFail($intent->getKey());

            if ($locked->status->isFinal()) {
                return $locked;
            }

            $locked->forceFill([
                'status' => $result->status,
                'provider_reference' => $result->providerReference,
                'redirect_url' => $result->redirectUrl,
                'instructions' => $result->instructions,
                'metadata' => $result->metadata,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
            ])->save();

            return $locked->refresh();
        }, attempts: 3);
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
        return $this->database->transaction(function () use (
            $order,
            $client,
            $method,
            $provider,
            $idempotencyKey,
        ): array {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->client_id !== $client->getKey()) {
                throw new PaymentConflictException('Vous ne pouvez pas payer cette commande.');
            }

            if ($lockedOrder->status !== OrderStatus::PENDING_PAYMENT) {
                throw new PaymentConflictException(
                    'Cette commande n’accepte plus de nouvelle initiation de paiement.'
                );
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
                if ($existing->order_id !== $lockedOrder->getKey()) {
                    throw new PaymentConflictException(
                        'Cette clé d’idempotence est déjà utilisée pour une autre opération.'
                    );
                }

                return [
                    'created' => false,
                    'intent' => $existing,
                    'fingerprint' => $fingerprint,
                ];
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

    private function markFailed(
        PaymentIntent $intent,
        string $code,
        string $message,
    ): void {
        DB::transaction(function () use ($intent, $code, $message): void {
            $locked = PaymentIntent::query()
                ->lockForUpdate()
                ->findOrFail($intent->getKey());

            if (! $locked->status->isFinal()) {
                $locked->forceFill([
                    'status' => PaymentStatus::FAILED,
                    'failure_code' => $code,
                    'failure_message' => $message,
                ])->save();
            }
        }, attempts: 3);
    }

    private function canonicalMoney(string $value): string
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', trim($value))) {
            throw new ValidationException([
                'payment' => 'Le montant retourné par le fournisseur est invalide.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
