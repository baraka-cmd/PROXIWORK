<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\PaymentConflictException;
use App\Models\ProfessionalProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Models\WithdrawalStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function request(
        ProfessionalProfile $professional,
        string $amount,
        string $currency,
        string $provider,
        string $destination,
        string $idempotencyKey,
    ): Withdrawal {
        $currency = strtoupper(trim($currency));
        $provider = strtolower(trim($provider));
        $destination = trim($destination);
        $idempotencyKey = trim($idempotencyKey);

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency' => 'La devise est invalide.']);
        }

        if ($provider === '' || strlen($provider) > 32 || preg_match('/^[a-z0-9_-]+$/', $provider) !== 1) {
            throw ValidationException::withMessages(['provider' => 'Le canal de retrait est invalide.']);
        }

        if ($destination === '' || mb_strlen($destination) > 191) {
            throw ValidationException::withMessages(['destination' => 'La destination de paiement est invalide.']);
        }

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clé de demande est invalide.']);
        }

        $value = $this->minor($amount);

        if ($value <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être strictement positif.']);
        }

        return DB::transaction(function () use ($professional, $currency, $provider, $destination, $idempotencyKey, $value): Withdrawal {
            $wallet = Wallet::query()
                ->where('professional_id', $professional->getKey())
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                throw new PaymentConflictException('Le portefeuille n’existe pas pour cette devise.');
            }

            $existing = Withdrawal::query()
                ->where('professional_id', $professional->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existingDestination = $existing->destination_ciphertext !== null
                    ? (string) $existing->destination_ciphertext
                    : (string) $existing->destination;

                if (
                    $this->minor((string) $existing->amount) !== $value
                    || $existing->currency !== $currency
                    || $existing->provider !== $provider
                    || ! hash_equals($existingDestination, $destination)
                ) {
                    throw new PaymentConflictException('La clé d’idempotence est déjà utilisée pour un autre retrait.');
                }

                return $existing;
            }

            if (strtolower((string) $wallet->status) !== 'active') {
                throw new PaymentConflictException('Ce portefeuille n’est pas autorisé à effectuer des retraits.');
            }

            $available = $this->minor((string) $wallet->available_balance);
            $lockedBefore = $this->minor((string) $wallet->locked_balance);

            if ($value > $available) {
                throw new PaymentConflictException('Solde disponible insuffisant.');
            }

            $availableAfter = $available - $value;
            $lockedAfter = $lockedBefore + $value;

            $wallet->forceFill([
                'available_balance' => $this->formatMinor($availableAfter),
                'locked_balance' => $this->formatMinor($lockedAfter),
            ])->save();

            $withdrawal = Withdrawal::query()->forceCreate([
                'wallet_id' => $wallet->getKey(),
                'professional_id' => $professional->getKey(),
                'provider' => $provider,
                // The legacy column keeps only a masked value. The original
                // destination is encrypted in destination_ciphertext.
                'destination' => $this->maskDestination($destination),
                'destination_ciphertext' => $destination,
                'status' => WithdrawalStatus::REQUESTED,
                'currency' => $currency,
                'amount' => $this->formatMinor($value),
                'fee_amount' => null,
                'net_amount' => null,
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);

            $this->recordLedger(
                wallet: $wallet,
                withdrawal: $withdrawal,
                bucket: 'available',
                direction: WalletTransactionDirection::DEBIT,
                amount: $value,
                before: $available,
                after: $availableAfter,
                idempotencyKey: 'withdrawal:'.$withdrawal->getKey().':reserve:available',
                description: 'Réservation du retrait — diminution du solde disponible.',
            );

            $this->recordLedger(
                wallet: $wallet,
                withdrawal: $withdrawal,
                bucket: 'locked',
                direction: WalletTransactionDirection::CREDIT,
                amount: $value,
                before: $lockedBefore,
                after: $lockedAfter,
                idempotencyKey: 'withdrawal:'.$withdrawal->getKey().':reserve:locked',
                description: 'Réservation du retrait — augmentation des fonds bloqués.',
            );

            $this->recordStatus(
                withdrawal: $withdrawal,
                from: null,
                to: WithdrawalStatus::REQUESTED,
                actorType: 'professional',
                actorUserId: $professional->user_id === null ? null : (int) $professional->user_id,
                reason: 'Demande soumise par le professionnel.',
                idempotencyKey: 'withdrawal:'.$withdrawal->getKey().':status:requested',
            );

            return $withdrawal->refresh();
        }, attempts: 3);
    }

    /**
     * Record that an authorized operator has actually started manual processing.
     * This does not call or simulate a payout provider.
     */
    public function markProcessing(
        Withdrawal $withdrawal,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): Withdrawal {
        return DB::transaction(function () use ($withdrawal, $actorUserId, $reason): Withdrawal {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());

            if ($locked->status === WithdrawalStatus::PROCESSING) {
                return $locked;
            }

            if ($locked->status !== WithdrawalStatus::REQUESTED) {
                throw new PaymentConflictException('Cette transition vers le traitement n’est pas autorisée.');
            }

            $locked->forceFill([
                'status' => WithdrawalStatus::PROCESSING,
                'processing_at' => now(),
                'reviewed_by' => $actorUserId,
                'review_reason' => $reason,
            ])->save();

            $this->recordStatus(
                withdrawal: $locked,
                from: WithdrawalStatus::REQUESTED,
                to: WithdrawalStatus::PROCESSING,
                actorType: $actorUserId === null ? 'system' : 'admin',
                actorUserId: $actorUserId,
                reason: $reason ?? 'Le traitement du retrait a été démarré.',
                idempotencyKey: 'withdrawal:'.$locked->getKey().':status:processing',
            );

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * Confirm success only after a trusted operator/provider has verified evidence.
     * The caller must enforce authorization and require a non-empty reference.
     */
    public function markSucceeded(
        Withdrawal $withdrawal,
        string $providerTransactionId,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): Withdrawal {
        $providerTransactionId = trim($providerTransactionId);

        if ($providerTransactionId === '' || mb_strlen($providerTransactionId) > 191) {
            throw ValidationException::withMessages([
                'provider_transaction_id' => 'Une référence de preuve est obligatoire pour confirmer le paiement.',
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $providerTransactionId, $actorUserId, $reason): Withdrawal {
            [$locked, $wallet] = $this->lockWithdrawalAndWallet($withdrawal);

            if ($locked->status === WithdrawalStatus::SUCCEEDED) {
                if ($locked->provider_transaction_id !== $providerTransactionId) {
                    throw new PaymentConflictException('Ce retrait est déjà réussi avec une autre référence de paiement.');
                }

                return $locked;
            }

            if (! in_array($locked->status, [WithdrawalStatus::PROCESSING, WithdrawalStatus::REVIEW_REQUIRED], true)) {
                throw new PaymentConflictException('Un retrait doit être en traitement ou à vérifier avant confirmation.');
            }

            $referenceAlreadyUsed = Withdrawal::query()
                ->where('provider', $locked->provider)
                ->where('provider_transaction_id', $providerTransactionId)
                ->where('id', '<>', $locked->getKey())
                ->exists();

            if ($referenceAlreadyUsed) {
                throw new PaymentConflictException('Cette référence de paiement est déjà associée à un autre retrait.');
            }

            $lockedBefore = $this->minor((string) $wallet->locked_balance);
            $value = $this->minor((string) $locked->amount);

            if ($value > $lockedBefore) {
                throw new PaymentConflictException('Le solde bloqué du portefeuille est incohérent.');
            }

            $lockedAfter = $lockedBefore - $value;

            $wallet->forceFill(['locked_balance' => $this->formatMinor($lockedAfter)])->save();

            $previousStatus = $locked->status;
            $locked->forceFill([
                'status' => WithdrawalStatus::SUCCEEDED,
                'provider_transaction_id' => $providerTransactionId,
                'completed_at' => now(),
                'review_reason' => $reason,
            ])->save();

            $this->recordLedger(
                wallet: $wallet,
                withdrawal: $locked,
                bucket: 'locked',
                direction: WalletTransactionDirection::DEBIT,
                amount: $value,
                before: $lockedBefore,
                after: $lockedAfter,
                idempotencyKey: 'withdrawal:'.$locked->getKey().':succeeded:locked',
                description: 'Retrait confirmé — sortie des fonds bloqués après rapprochement.',
                metadata: ['provider_transaction_id' => $providerTransactionId],
            );

            $this->recordStatus(
                withdrawal: $locked,
                from: $previousStatus,
                to: WithdrawalStatus::SUCCEEDED,
                actorType: $actorUserId === null ? 'system' : 'admin',
                actorUserId: $actorUserId,
                reason: $reason ?? 'Paiement confirmé avec preuve de rapprochement.',
                idempotencyKey: 'withdrawal:'.$locked->getKey().':status:succeeded',
                metadata: ['provider_transaction_id' => $providerTransactionId],
            );

            return $locked->refresh();
        }, attempts: 3);
    }

    public function markFailed(
        Withdrawal $withdrawal,
        string $code,
        string $message,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): Withdrawal {
        $code = trim($code);
        $message = trim($message);

        if ($code === '' || $message === '') {
            throw ValidationException::withMessages([
                'failure_message' => 'Le code et le motif détaillé de l’échec sont obligatoires.',
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $code, $message, $actorUserId, $reason): Withdrawal {
            [$locked, $wallet] = $this->lockWithdrawalAndWallet($withdrawal);

            if ($locked->status === WithdrawalStatus::FAILED) {
                if ($locked->failure_code !== $code) {
                    throw new PaymentConflictException('Ce retrait est déjà échoué avec un autre motif.');
                }

                return $locked;
            }

            if (! in_array($locked->status, [WithdrawalStatus::PROCESSING, WithdrawalStatus::REVIEW_REQUIRED], true)) {
                throw new PaymentConflictException('Un retrait doit être en traitement ou à vérifier avant de confirmer son échec.');
            }

            return $this->releaseReservation(
                withdrawal: $locked,
                wallet: $wallet,
                targetStatus: WithdrawalStatus::FAILED,
                code: $code,
                message: $message,
                actorUserId: $actorUserId,
                reason: $reason ?? $message,
            );
        }, attempts: 3);
    }

    /**
     * Reject a request before payout execution and release its reservation once.
     */
    public function reject(
        Withdrawal $withdrawal,
        string $code,
        string $reason,
        int $actorUserId,
    ): Withdrawal {
        $code = trim($code);
        $reason = trim($reason);

        if ($code === '' || $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Un code et un motif de refus sont obligatoires.',
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $code, $reason, $actorUserId): Withdrawal {
            [$locked, $wallet] = $this->lockWithdrawalAndWallet($withdrawal);

            if ($locked->status === WithdrawalStatus::REJECTED) {
                if ($locked->failure_code !== $code) {
                    throw new PaymentConflictException('Ce retrait a déjà été refusé avec un autre motif.');
                }

                return $locked;
            }

            if ($locked->status !== WithdrawalStatus::REQUESTED) {
                throw new PaymentConflictException('Seule une demande en attente peut être refusée avant exécution.');
            }

            return $this->releaseReservation(
                withdrawal: $locked,
                wallet: $wallet,
                targetStatus: WithdrawalStatus::REJECTED,
                code: $code,
                message: $reason,
                actorUserId: $actorUserId,
                reason: $reason,
            );
        }, attempts: 3);
    }

    /**
     * A timeout or missing callback is not a confirmed failure. Keep funds locked.
     */
    public function markReviewRequired(
        Withdrawal $withdrawal,
        string $reason,
        ?int $actorUserId = null,
    ): Withdrawal {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif de vérification est obligatoire.']);
        }

        return DB::transaction(function () use ($withdrawal, $reason, $actorUserId): Withdrawal {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());

            if ($locked->status === WithdrawalStatus::REVIEW_REQUIRED) {
                return $locked;
            }

            if ($locked->status !== WithdrawalStatus::PROCESSING) {
                throw new PaymentConflictException('Seul un retrait en traitement peut passer en vérification.');
            }

            $locked->forceFill([
                'status' => WithdrawalStatus::REVIEW_REQUIRED,
                'review_required_at' => now(),
                'reviewed_by' => $actorUserId,
                'review_reason' => $reason,
            ])->save();

            $this->recordStatus(
                withdrawal: $locked,
                from: WithdrawalStatus::PROCESSING,
                to: WithdrawalStatus::REVIEW_REQUIRED,
                actorType: $actorUserId === null ? 'system' : 'admin',
                actorUserId: $actorUserId,
                reason: $reason,
                idempotencyKey: 'withdrawal:'.$locked->getKey().':status:review-required',
            );

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * Cancellation is only safe before processing starts.
     */
    public function cancel(
        Withdrawal $withdrawal,
        int $actorUserId,
        string $reason = 'Annulé par le professionnel avant le traitement.',
    ): Withdrawal {
        return DB::transaction(function () use ($withdrawal, $actorUserId, $reason): Withdrawal {
            [$locked, $wallet] = $this->lockWithdrawalAndWallet($withdrawal);

            if ($locked->status === WithdrawalStatus::CANCELLED) {
                return $locked;
            }

            if ($locked->status !== WithdrawalStatus::REQUESTED) {
                throw new PaymentConflictException('Ce retrait ne peut plus être annulé après le début du traitement.');
            }

            return $this->releaseReservation(
                withdrawal: $locked,
                wallet: $wallet,
                targetStatus: WithdrawalStatus::CANCELLED,
                code: null,
                message: null,
                actorUserId: $actorUserId,
                reason: $reason,
            );
        }, attempts: 3);
    }

    private function releaseReservation(
        Withdrawal $withdrawal,
        Wallet $wallet,
        WithdrawalStatus $targetStatus,
        ?string $code,
        ?string $message,
        ?int $actorUserId,
        string $reason,
    ): Withdrawal {
        $lockedBefore = $this->minor((string) $wallet->locked_balance);
        $availableBefore = $this->minor((string) $wallet->available_balance);
        $value = $this->minor((string) $withdrawal->amount);

        if ($value > $lockedBefore) {
            throw new PaymentConflictException('Le solde bloqué du portefeuille est incohérent.');
        }

        $lockedAfter = $lockedBefore - $value;
        $availableAfter = $availableBefore + $value;

        $wallet->forceFill([
            'locked_balance' => $this->formatMinor($lockedAfter),
            'available_balance' => $this->formatMinor($availableAfter),
        ])->save();

        $previousStatus = $withdrawal->status;
        $withdrawal->forceFill([
            'status' => $targetStatus,
            'failure_code' => $code,
            'failure_message' => $message,
            'completed_at' => in_array($targetStatus, [WithdrawalStatus::FAILED, WithdrawalStatus::REJECTED], true) ? now() : null,
            'reviewed_by' => $actorUserId,
            'review_reason' => $reason,
        ])->save();

        $suffix = match ($targetStatus) {
            WithdrawalStatus::CANCELLED => 'cancelled',
            WithdrawalStatus::REJECTED => 'rejected',
            default => 'failed',
        };

        $this->recordLedger(
            wallet: $wallet,
            withdrawal: $withdrawal,
            bucket: 'locked',
            direction: WalletTransactionDirection::DEBIT,
            amount: $value,
            before: $lockedBefore,
            after: $lockedAfter,
            idempotencyKey: 'withdrawal:'.$withdrawal->getKey().':'.$suffix.':locked',
            description: 'Libération de la réservation — diminution des fonds bloqués.',
        );

        $this->recordLedger(
            wallet: $wallet,
            withdrawal: $withdrawal,
            bucket: 'available',
            direction: WalletTransactionDirection::CREDIT,
            amount: $value,
            before: $availableBefore,
            after: $availableAfter,
            idempotencyKey: 'withdrawal:'.$withdrawal->getKey().':'.$suffix.':available',
            description: 'Libération de la réservation — retour au solde disponible.',
        );

        $this->recordStatus(
            withdrawal: $withdrawal,
            from: $previousStatus,
            to: $targetStatus,
            actorType: $actorUserId === null ? 'system' : ($targetStatus === WithdrawalStatus::CANCELLED ? 'professional' : 'admin'),
            actorUserId: $actorUserId,
            reason: $reason,
            idempotencyKey: 'withdrawal:'.$withdrawal->getKey().':status:'.$targetStatus->value,
            metadata: ['failure_code' => $code],
        );

        return $withdrawal->refresh();
    }

    /**
     * Always lock wallet before withdrawal for financial transitions. This matches
     * request() and prevents a retry-versus-settlement lock-order deadlock.
     *
     * @return array{0: Withdrawal, 1: Wallet}
     */
    private function lockWithdrawalAndWallet(Withdrawal $withdrawal): array
    {
        $withdrawalId = $withdrawal->getKey();
        $walletId = Withdrawal::query()->whereKey($withdrawalId)->value('wallet_id');

        if ($walletId === null) {
            Withdrawal::query()->findOrFail($withdrawalId);
        }

        $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);
        $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawalId);

        if ((int) $locked->wallet_id !== (int) $wallet->getKey()) {
            throw new PaymentConflictException('Le portefeuille associé au retrait a changé de façon inattendue.');
        }

        return [$locked, $wallet];
    }

    private function recordLedger(
        Wallet $wallet,
        Withdrawal $withdrawal,
        string $bucket,
        WalletTransactionDirection $direction,
        int $amount,
        int $before,
        int $after,
        string $idempotencyKey,
        string $description,
        array $metadata = [],
    ): WalletTransaction {
        $existing = WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return WalletTransaction::query()->forceCreate([
            'wallet_id' => $wallet->getKey(),
            'type' => WalletTransactionType::WITHDRAWAL,
            'direction' => $direction,
            'currency' => $withdrawal->currency,
            'amount' => $this->formatMinor($amount),
            'balance_before' => $this->formatMinor($before),
            'balance_after' => $this->formatMinor($after),
            'balance_bucket' => $bucket,
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->getKey(),
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function recordStatus(
        Withdrawal $withdrawal,
        ?WithdrawalStatus $from,
        WithdrawalStatus $to,
        string $actorType,
        ?int $actorUserId,
        ?string $reason,
        string $idempotencyKey,
        array $metadata = [],
    ): WithdrawalStatusHistory {
        $existing = WithdrawalStatusHistory::query()
            ->where('withdrawal_id', $withdrawal->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return WithdrawalStatusHistory::query()->forceCreate([
            'withdrawal_id' => $withdrawal->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function minor(string $value): int
    {
        $value = trim($value);

        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new \InvalidArgumentException('Montant financier invalide.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '0');
        $normalizedWhole = ltrim($whole, '0') ?: '0';

        if (strlen($normalizedWhole) > 16) {
            throw new \InvalidArgumentException('Montant financier hors limites techniques.');
        }

        $minor = ((int) $normalizedWhole * 100) + (int) str_pad($decimal, 2, '0');

        if ($minor < 0) {
            throw new \InvalidArgumentException('Montant financier invalide.');
        }

        return $minor;
    }

    private function formatMinor(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Un solde ne peut pas être négatif.');
        }

        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    private function maskDestination(string $destination): string
    {
        $suffix = mb_substr($destination, -4);

        return str_repeat('*', max(4, mb_strlen($destination) - mb_strlen($suffix))).$suffix;
    }
}
