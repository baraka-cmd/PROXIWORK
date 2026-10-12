<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentConflictException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRefundRequestService
{
    public function request(Payment $payment, User $client, string $reason): PaymentRefund
    {
        return DB::transaction(function () use ($payment, $client, $reason): PaymentRefund {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->client_id !== $client->getKey()) {
                throw new PaymentConflictException('Vous ne pouvez pas demander le remboursement de ce paiement.');
            }

            if ($lockedPayment->status !== PaymentStatus::SUCCEEDED) {
                throw new PaymentConflictException('Seul un paiement confirmé peut faire l’objet d’une demande de remboursement.');
            }

            $openRequest = PaymentRefund::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->whereIn('status', [
                    PaymentRefundStatus::REQUESTED->value,
                    PaymentRefundStatus::PROCESSING->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($openRequest !== null) {
                return $openRequest;
            }

            return PaymentRefund::query()->forceCreate([
                'reference' => 'RFD-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
                'payment_id' => $lockedPayment->getKey(),
                'client_id' => $client->getKey(),
                // This is the amount requested for review, not a completed refund.
                // An authorized review workflow must decide the actual refundable amount.
                'amount' => $lockedPayment->amount,
                'currency' => strtoupper($lockedPayment->currency),
                'status' => PaymentRefundStatus::REQUESTED,
                'reason' => trim($reason),
                'requested_at' => now(),
            ]);
        }, attempts: 3);
    }
}
