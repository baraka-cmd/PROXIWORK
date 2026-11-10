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
use App\Notifications\PaymentRefundStatusChanged;
use App\Services\Payment\PaymentEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRefundAdminService
{
    public function __construct(private readonly PaymentEventService $events) {}

    public function startProcessing(PaymentRefund $refund, int $actorId, string $note): PaymentRefund
    {
        return $this->transition($refund, PaymentRefundStatus::PROCESSING, $actorId, $note);
    }

    public function reject(PaymentRefund $refund, int $actorId, string $note): PaymentRefund
    {
        return $this->transition($refund, PaymentRefundStatus::REJECTED, $actorId, $note);
    }

    public function markSucceeded(PaymentRefund $refund, int $actorId, string $providerReference, string $note): PaymentRefund
    {
        $providerReference = trim($providerReference);

        if ($providerReference === '' || mb_strlen($providerReference) > 191) {
            throw ValidationException::withMessages([
                'provider_reference' => 'Une référence externe vérifiable est obligatoire.',
            ]);
        }

        return DB::transaction(function () use ($refund, $actorId, $providerReference, $note): PaymentRefund {
            [$locked, $payment, $transaction] = $this->lockContext($refund);

            if ($locked->status === PaymentRefundStatus::SUCCEEDED) {
                if ($locked->provider_reference !== $providerReference) {
                    throw new PaymentConflictException('Ce remboursement est déjà confirmé avec une autre référence.');
                }

                return $locked;
            }

            if ($locked->status !== PaymentRefundStatus::PROCESSING) {
                throw new PaymentConflictException('Une demande doit être en cours de traitement avant la confirmation du remboursement.');
            }

            if ($payment->status !== PaymentStatus::SUCCEEDED) {
                throw new PaymentConflictException('Le paiement initial n’est plus dans un état permettant cette confirmation.');
            }

            $referenceUsed = PaymentRefund::query()
                ->where('provider_reference', $providerReference)
                ->where('id', '<>', $locked->getKey())
                ->exists();

            if ($referenceUsed) {
                throw new PaymentConflictException('Cette référence externe est déjà associée à un autre remboursement.');
            }

            $alreadyRefunded = PaymentRefund::query()
                ->where('payment_id', $payment->getKey())
                ->where('status', PaymentRefundStatus::SUCCEEDED->value)
                ->where('id', '<>', $locked->getKey())
                ->sum('amount');

            if ($this->toMinor((string) $alreadyRefunded) + $this->toMinor((string) $locked->amount) > $this->toMinor((string) $payment->amount)) {
                throw new PaymentConflictException('Le cumul des remboursements dépasserait le montant initialement payé.');
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => PaymentRefundStatus::SUCCEEDED,
                'provider_reference' => $providerReference,
                'review_note' => trim($note),
                'processed_at' => now(),
            ])->save();

            $this->recordEvent($transaction, $locked, $from, PaymentRefundStatus::SUCCEEDED, $actorId, $note, $providerReference);
            $this->notifyClient($locked, $from, PaymentRefundStatus::SUCCEEDED);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function markFailed(PaymentRefund $refund, int $actorId, string $note, ?string $providerReference = null): PaymentRefund
    {
        return DB::transaction(function () use ($refund, $actorId, $note, $providerReference): PaymentRefund {
            [$locked, , $transaction] = $this->lockContext($refund);

            if ($locked->status === PaymentRefundStatus::FAILED) {
                return $locked;
            }

            if ($locked->status !== PaymentRefundStatus::PROCESSING) {
                throw new PaymentConflictException('Seule une demande en cours de traitement peut être déclarée en échec.');
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => PaymentRefundStatus::FAILED,
                'provider_reference' => $providerReference ?: $locked->provider_reference,
                'review_note' => trim($note),
                'processed_at' => now(),
            ])->save();

            $this->recordEvent($transaction, $locked, $from, PaymentRefundStatus::FAILED, $actorId, $note, $providerReference);
            $this->notifyClient($locked, $from, PaymentRefundStatus::FAILED);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function transition(PaymentRefund $refund, PaymentRefundStatus $to, int $actorId, string $note): PaymentRefund
    {
        return DB::transaction(function () use ($refund, $to, $actorId, $note): PaymentRefund {
            [$locked, , $transaction] = $this->lockContext($refund);

            if ($locked->status === $to) {
                return $locked;
            }

            if ($to === PaymentRefundStatus::PROCESSING && $locked->status !== PaymentRefundStatus::REQUESTED) {
                throw new PaymentConflictException('Seule une demande reçue peut être prise en charge.');
            }

            if ($to === PaymentRefundStatus::REJECTED && $locked->status !== PaymentRefundStatus::REQUESTED) {
                throw new PaymentConflictException('Une demande ne peut être refusée qu’avant le début de son traitement.');
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => $to,
                'review_note' => trim($note),
                'processed_at' => $to === PaymentRefundStatus::REJECTED ? now() : null,
            ])->save();

            $this->recordEvent($transaction, $locked, $from, $to, $actorId, $note);
            $this->notifyClient($locked, $from, $to);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function lockContext(PaymentRefund $refund): array
    {
        // Match the client request lock order (payment first, refund second) to reduce deadlocks.
        $paymentId = PaymentRefund::query()->whereKey($refund->getKey())->value('payment_id');
        $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
        $locked = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->getKey());

        $transaction = PaymentTransaction::query()
            ->where('payment_id', $payment->getKey())
            ->where('status', 'succeeded')
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($transaction === null) {
            throw new PaymentConflictException('Aucune transaction de paiement confirmée ne permet de rapprocher ce remboursement.');
        }

        return [$locked, $payment, $transaction];
    }

    private function notifyClient(PaymentRefund $refund, PaymentRefundStatus $from, PaymentRefundStatus $to): void
    {
        $client = User::query()->find($refund->client_id);

        if ($client !== null) {
            $client->notify(new PaymentRefundStatusChanged(
                refundId: (int) $refund->getKey(),
                paymentId: (int) $refund->payment_id,
                status: $to,
                previousStatus: $from,
            ));
        }
    }

    private function toMinor(string $amount): int
    {
        if (preg_match('/^(\\d+)(?:\\.(\\d{1,2}))?$/', trim($amount), $matches) !== 1) {
            throw new PaymentConflictException('Un montant financier enregistré est invalide.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function recordEvent(
        PaymentTransaction $transaction,
        PaymentRefund $refund,
        PaymentRefundStatus $from,
        PaymentRefundStatus $to,
        int $actorId,
        string $note,
        ?string $providerReference = null,
    ): void {
        $this->events->record(
            $transaction,
            'refund.'.$to->value,
            $from->value,
            $to->value,
            'admin',
            [
                'refund_reference' => $refund->reference,
                'admin_user_id' => $actorId,
                'review_note_hash' => hash('sha256', trim($note)),
                'review_note' => mb_substr(trim($note), 0, 2000),
                'provider_reference' => $providerReference,
            ],
            'refund:'.$refund->getKey().':'.$to->value,
        );
    }
}
