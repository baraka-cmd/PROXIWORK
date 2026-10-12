<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentReceiptService
{
    public function issueFor(Payment $payment, PaymentTransaction $transaction): PaymentReceipt
    {
        return DB::transaction(function () use ($payment, $transaction): PaymentReceipt {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status !== PaymentStatus::SUCCEEDED
                || $transaction->payment_id !== $lockedPayment->getKey()
                || $transaction->status !== PaymentTransactionStatus::SUCCEEDED
                || $this->money((string) $transaction->amount) !== $this->money((string) $lockedPayment->amount)
                || strtoupper($transaction->currency) !== strtoupper($lockedPayment->currency)
                || $transaction->provider !== $lockedPayment->provider->value) {
                throw new \LogicException('Un reçu ne peut être émis que pour une transaction confirmée.');
            }

            $existing = PaymentReceipt::query()->where('payment_id', $lockedPayment->getKey())->first();
            if ($existing !== null) {
                return $existing;
            }

            $lockedPayment->loadMissing([
                'client',
                'order.items',
                'order.professional.user',
            ]);

            $order = $lockedPayment->order;
            $snapshot = [
                'mode' => 'simulation',
                'simulation_notice' => 'SIMULATION — aucune transaction financière réelle n’a été effectuée.',
                'payment_reference' => 'PAY-'.str_pad((string) $lockedPayment->getKey(), 8, '0', STR_PAD_LEFT),
                'order_reference' => $order?->order_number ?? '#'.$lockedPayment->order_id,
                'client_name' => $lockedPayment->client?->name ?? 'Client PROXIWORK',
                'professional_name' => $order?->professional?->user?->name,
                'service_title' => $order?->items?->first()?->service_title ?? 'Prestation de service',
                'amount' => (string) $lockedPayment->amount,
                'currency' => strtoupper($lockedPayment->currency),
                'method' => $lockedPayment->method->value,
                'provider' => $lockedPayment->provider->value,
                'payment_status' => PaymentStatus::SUCCEEDED->value,
                'transaction_reference' => $lockedPayment->provider->value === 'fake'
                    ? 'SIM-TXN-'.str_pad((string) $transaction->getKey(), 8, '0', STR_PAD_LEFT)
                    : ($transaction->provider_reference ?? $transaction->provider_transaction_id ?? 'TXN-'.$transaction->getKey()),
                'confirmed_at' => optional($lockedPayment->paid_at)->toIso8601String(),
                'issued_at' => now()->toIso8601String(),
            ];

            $serialized = json_encode($this->canonicalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            return PaymentReceipt::query()->forceCreate([
                'receipt_number' => 'SIM-'.now()->format('Ymd').'-'.Str::upper(Str::random(12)),
                'payment_id' => $lockedPayment->getKey(),
                'payment_transaction_id' => $transaction->getKey(),
                'mode' => 'simulation',
                'payload' => $snapshot,
                'content_hash' => hash('sha256', $serialized),
                'issued_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function verify(PaymentReceipt $receipt): bool
    {
        $serialized = json_encode(
            $this->canonicalize($receipt->payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (! hash_equals($receipt->content_hash, hash('sha256', $serialized))) {
            return false;
        }

        $receipt->loadMissing(['payment', 'transaction']);

        $payment = $receipt->payment;
        $transaction = $receipt->transaction;
        $payload = $receipt->payload ?? [];

        if ($receipt->mode !== 'simulation'
            || $payment?->status !== PaymentStatus::SUCCEEDED
            || $transaction?->status !== PaymentTransactionStatus::SUCCEEDED
            || $transaction?->payment_id !== $receipt->payment_id) {
            return false;
        }

        $expectedTransactionReference = $payment->provider->value === 'fake'
            ? 'SIM-TXN-'.str_pad((string) $transaction->getKey(), 8, '0', STR_PAD_LEFT)
            : ($transaction->provider_reference ?? $transaction->provider_transaction_id ?? 'TXN-'.$transaction->getKey());

        return ($payload['payment_reference'] ?? null) === 'PAY-'.str_pad((string) $payment->getKey(), 8, '0', STR_PAD_LEFT)
            && $this->money((string) ($payload['amount'] ?? '')) === $this->money((string) $payment->amount)
            && strtoupper((string) ($payload['currency'] ?? '')) === strtoupper($payment->currency)
            && ($payload['transaction_reference'] ?? null) === $expectedTransactionReference
            && ($payload['payment_status'] ?? null) === PaymentStatus::SUCCEEDED->value;
    }

    private function money(string $value): string
    {
        if (preg_match('/^\\d+(?:\\.\\d{1,2})?$/', trim($value)) !== 1) {
            return '__invalid__';
        }

        [$whole, $decimal] = array_pad(explode('.', trim($value), 2), 2, '0');

        return $whole.'.'.str_pad($decimal, 2, '0');
    }

    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    public function findOrIssueFor(Payment $payment): ?PaymentReceipt
    {
        $existing = PaymentReceipt::query()->where('payment_id', $payment->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        if ($payment->status !== PaymentStatus::SUCCEEDED) {
            return null;
        }

        $transaction = $payment->transactions()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->first();

        return $transaction === null ? null : $this->issueFor($payment, $transaction);
    }
}
