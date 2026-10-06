<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PaymentWebhookService
{
    public function handle(
        PaymentProvider $provider,
        string $signature,
        string $eventId,
        string $transactionId,
        string $amount,
        string $currency,
        PaymentStatus $status,
        ?string $failureCode,
        ?string $failureMessage,
        array $metadata,
        string $rawBody,
    ): PaymentTransaction {
        $this->verifySignature($provider, $signature, $rawBody);

        return DB::transaction(function () use ($provider, $eventId, $transactionId, $amount, $currency, $status, $failureCode, $failureMessage, $metadata): PaymentTransaction {
            $duplicate = PaymentTransaction::query()
                ->where('provider', $provider->value)
                ->where('provider_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            $transaction = PaymentTransaction::query()
                ->where('provider', $provider->value)
                ->where('provider_transaction_id', $transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status->isFinal()) {
                $transaction->forceFill(['provider_event_id' => $eventId])->save();

                return $transaction->refresh();
            }

            if ($this->money($amount) !== $this->money((string) $transaction->amount)
                || strtoupper($currency) !== strtoupper($transaction->currency)) {
                throw ValidationException::withMessages([
                    'payment' => 'Le callback ne correspond pas au montant ou à la devise de la transaction.',
                ]);
            }

            $newStatus = PaymentTransactionStatus::from($status->value);

            $transaction->forceFill([
                'provider_event_id' => $eventId,
                'status' => $newStatus,
                'response_metadata' => $metadata,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'processed_at' => $status === PaymentStatus::SUCCEEDED ? now() : $transaction->processed_at,
                'failed_at' => $status === PaymentStatus::FAILED ? now() : $transaction->failed_at,
            ])->save();

            $payment = Payment::query()->lockForUpdate()->findOrFail($transaction->payment_id);
            $payment->forceFill([
                'status' => $status,
                'paid_at' => $status === PaymentStatus::SUCCEEDED ? now() : $payment->paid_at,
                'failed_at' => $status === PaymentStatus::FAILED ? now() : $payment->failed_at,
            ])->save();

            $intent = $payment->intent()->lockForUpdate()->first();
            if ($intent !== null) {
                $intent->forceFill([
                    'status' => $status,
                    'provider_reference' => $transaction->provider_reference,
                    'failure_code' => $failureCode,
                    'failure_message' => $failureMessage,
                ])->save();
            }

            if ($status === PaymentStatus::SUCCEEDED) {
                $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

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
                        'changed_by' => $payment->client_id,
                        'reason' => 'payment_webhook_succeeded',
                        'metadata' => [
                            'payment_id' => $payment->getKey(),
                            'payment_transaction_id' => $transaction->getKey(),
                            'provider_event_id' => $eventId,
                        ],
                    ]);
                }
            }

            return $transaction->refresh();
        }, attempts: 3);
    }

    private function verifySignature(PaymentProvider $provider, string $signature, string $rawBody): void
    {
        $secret = (string) config('payment.webhooks.secrets.'.$provider->value, '');

        if ($secret === '' || $signature === '') {
            throw new UnauthorizedHttpException('', 'Signature de webhook absente ou non configurée.');
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new UnauthorizedHttpException('', 'Signature de webhook invalide.');
        }
    }

    private function money(string $value): string
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', trim($value))) {
            return '__invalid__';
        }

        [$whole, $decimal] = array_pad(explode('.', trim($value), 2), 2, '0');

        return $whole.'.'.str_pad($decimal, 2, '0');
    }
}
