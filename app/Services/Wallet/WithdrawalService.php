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
        return DB::transaction(function () use ($professional, $amount, $currency, $provider, $destination, $idempotencyKey): Withdrawal {
            $wallet = Wallet::query()
                ->where('professional_id', $professional->getKey())
                ->where('currency', strtoupper($currency))
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
                if (
                    $existing->amount !== $amount
                    || $existing->currency !== strtoupper($currency)
                    || $existing->provider !== $provider
                    || $existing->destination !== $destination
                ) {
                    throw new PaymentConflictException('La clé d’idempotence est déjà utilisée pour un autre retrait.');
                }

                return $existing;
            }

            $available = $this->minor((string) $wallet->available_balance);
            $value = $this->minor($amount);

            if ($value <= 0) {
                throw ValidationException::withMessages(['amount' => 'Le montant doit être strictement positif.']);
            }

            if ($value > $available) {
                throw new PaymentConflictException('Solde disponible insuffisant.');
            }

            $wallet->forceFill([
                'available_balance' => $this->formatMinor($available - $value),
                'locked_balance' => $this->formatMinor($this->minor((string) $wallet->locked_balance) + $value),
            ])->save();

            $withdrawal = Withdrawal::query()->forceCreate([
                'wallet_id' => $wallet->getKey(),
                'professional_id' => $professional->getKey(),
                'provider' => $provider,
                'destination' => $destination,
                'status' => WithdrawalStatus::REQUESTED,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);

            WalletTransaction::query()->forceCreate([
                'wallet_id' => $wallet->getKey(),
                'type' => WalletTransactionType::WITHDRAWAL,
                'direction' => WalletTransactionDirection::DEBIT,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'balance_before' => $this->formatMinor($available),
                'balance_after' => $this->formatMinor($available - $value),
                'reference_type' => Withdrawal::class,
                'reference_id' => $withdrawal->getKey(),
                'idempotency_key' => 'withdrawal:'.$withdrawal->getKey(),
                'description' => 'Blocage du montant demandé pour retrait.',
            ]);

            return $withdrawal;
        }, attempts: 3);
    }

    public function markSucceeded(Withdrawal $withdrawal, string $providerTransactionId): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $providerTransactionId): Withdrawal {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());

            if ($locked->status->isFinal()) {
                return $locked;
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($locked->wallet_id);
            $lockedAmount = $this->minor((string) $wallet->locked_balance);
            $value = $this->minor((string) $locked->amount);

            if ($value > $lockedAmount) {
                throw new PaymentConflictException('Le solde bloqué du portefeuille est incohérent.');
            }

            $wallet->forceFill([
                'locked_balance' => $this->formatMinor($lockedAmount - $value),
            ])->save();

            $locked->forceFill([
                'status' => WithdrawalStatus::SUCCEEDED,
                'provider_transaction_id' => $providerTransactionId,
                'completed_at' => now(),
            ])->save();

            return $locked->refresh();
        }, attempts: 3);
    }

    public function markFailed(Withdrawal $withdrawal, string $code, string $message): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $code, $message): Withdrawal {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());

            if ($locked->status->isFinal()) {
                return $locked;
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($locked->wallet_id);
            $lockedAmount = $this->minor((string) $wallet->locked_balance);
            $value = $this->minor((string) $locked->amount);

            if ($value > $lockedAmount) {
                throw new PaymentConflictException('Le solde bloqué du portefeuille est incohérent.');
            }

            $available = $this->minor((string) $wallet->available_balance);

            $wallet->forceFill([
                'locked_balance' => $this->formatMinor($lockedAmount - $value),
                'available_balance' => $this->formatMinor($available + $value),
            ])->save();

            $locked->forceFill([
                'status' => WithdrawalStatus::FAILED,
                'failure_code' => $code,
                'failure_message' => $message,
            ])->save();

            return $locked->refresh();
        }, attempts: 3);
    }

    private function minor(string $value): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', trim($value))) {
            throw new \InvalidArgumentException('Montant financier invalide.');
        }

        [$whole, $decimal] = array_pad(explode('.', trim($value), 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function formatMinor(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }
}
