<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentConflictException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRefundRequestService
{
    public function __construct(
        private readonly PaymentEventService $events,
    ) {}

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

            $alreadyRefundedCents = PaymentRefund::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->where('status', PaymentRefundStatus::SUCCEEDED->value)
                ->get(['amount'])
                ->sum(fn (PaymentRefund $refund): int => $this->toMinorUnits((string) $refund->amount));
            $remainingCents = $this->toMinorUnits((string) $lockedPayment->amount) - $alreadyRefundedCents;

            if ($remainingCents <= 0) {
                throw new PaymentConflictException('Aucun montant supplémentaire ne peut être demandé pour ce paiement.');
            }

            $requestedAmount = intdiv($remainingCents, 100).'.'.str_pad((string) ($remainingCents % 100), 2, '0', STR_PAD_LEFT);

            $refund = PaymentRefund::query()->forceCreate([
                'reference' => 'RFD-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
                'payment_id' => $lockedPayment->getKey(),
                'client_id' => $client->getKey(),
                // This is a requested amount, not a completed refund.
                // An authorized review workflow must confirm the actual refundable amount.
                'amount' => $requestedAmount,
                'currency' => strtoupper($lockedPayment->currency),
                'status' => PaymentRefundStatus::REQUESTED,
                'reason' => trim($reason),
                'requested_at' => now(),
            ]);

            $transaction = PaymentTransaction::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->where('status', 'succeeded')
                ->orderByDesc('processed_at')
                ->orderByDesc('id')
                ->firstOrFail();

            $this->events->record(
                $transaction,
                'refund.requested',
                PaymentStatus::SUCCEEDED->value,
                PaymentRefundStatus::REQUESTED->value,
                'client',
                ['refund_reference' => $refund->reference, 'reason_hash' => hash('sha256', $refund->reason)],
                'refund-requested-'.$refund->getKey(),
            );

            return $refund;
        }, attempts: 3);
    }

    private function toMinorUnits(string $amount): int
    {
        if (preg_match('/^(\\d+)(?:\\.(\\d{1,2}))?$/', trim($amount), $matches) !== 1) {
            throw new PaymentConflictException('Le montant enregistré ne peut pas être utilisé pour un remboursement.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
