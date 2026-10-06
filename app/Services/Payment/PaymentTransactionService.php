<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\PaymentConflictException;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Payments\DTO\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentTransactionService
{
    public function ensure(
        Payment $payment,
        string $idempotencyKey,
        string $amount,
        string $currency,
        string $provider,
    ): PaymentTransaction {
        return DB::transaction(function () use ($payment, $idempotencyKey, $amount, $currency, $provider): PaymentTransaction {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            $existing = PaymentTransaction::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->amount !== $amount
                    || strtoupper($existing->currency) !== strtoupper($currency)
                    || $existing->provider !== $provider) {
                    throw new PaymentConflictException('La clé d’idempotence est déjà associée à une autre transaction.');
                }

                return $existing;
            }

            if ($lockedPayment->status === PaymentStatus::SUCCEEDED) {
                throw new PaymentConflictException('Le paiement de cette commande est déjà confirmé.');
            }

            return PaymentTransaction::query()->forceCreate([
                'payment_id' => $lockedPayment->getKey(),
                'provider' => $provider,
                'idempotency_key' => $idempotencyKey,
                'status' => PaymentTransactionStatus::INITIATED,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'initiated_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function applyResult(
        PaymentTransaction $transaction,
        PaymentResult $result,
    ): PaymentTransaction {
        return DB::transaction(function () use ($transaction, $result): PaymentTransaction {
            $locked = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->getKey());

            if ($locked->status->isFinal()) {
                return $locked;
            }

            if ($this->money($result->amount) !== $this->money((string) $locked->amount)
                || strtoupper($result->currency) !== strtoupper($locked->currency)) {
                $locked->forceFill([
                    'status' => PaymentTransactionStatus::FAILED,
                    'failure_code' => 'PROVIDER_AMOUNT_MISMATCH',
                    'failure_message' => 'Le fournisseur a retourné un montant ou une devise incohérente.',
                    'failed_at' => now(),
                    'response_metadata' => $result->metadata,
                ])->save();

                throw ValidationException::withMessages([
                    'payment' => 'La réponse du fournisseur de paiement est incohérente.',
                ]);
            }

            $locked->forceFill([
                'status' => PaymentTransactionStatus::from($result->status->value),
                'provider_transaction_id' => $result->providerReference,
                'provider_reference' => $result->providerReference,
                'response_metadata' => $result->metadata,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
                'processing_at' => $result->status === PaymentStatus::PROCESSING ? now() : $locked->processing_at,
                'processed_at' => $result->status === PaymentStatus::SUCCEEDED ? now() : $locked->processed_at,
                'failed_at' => $result->status === PaymentStatus::FAILED ? now() : $locked->failed_at,
            ])->save();

            return $locked->refresh();
        }, attempts: 3);
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
