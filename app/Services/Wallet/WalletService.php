<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Exceptions\PaymentConflictException;
use App\Models\ProfessionalProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function getOrCreate(ProfessionalProfile $professional, string $currency): Wallet
    {
        return DB::transaction(function () use ($professional, $currency): Wallet {
            ProfessionalProfile::query()->lockForUpdate()->findOrFail($professional->getKey());

            $wallet = Wallet::query()
                ->where('professional_id', $professional->getKey())
                ->where('currency', strtoupper($currency))
                ->lockForUpdate()
                ->first();

            if ($wallet !== null) {
                return $wallet;
            }

            return Wallet::query()->forceCreate([
                'professional_id' => $professional->getKey(),
                'currency' => strtoupper($currency),
                'available_balance' => 0,
                'pending_balance' => 0,
                'locked_balance' => 0,
                'status' => 'active',
            ]);
        }, attempts: 3);
    }

    public function creditPending(
        ProfessionalProfile $professional,
        string $amount,
        string $currency,
        WalletTransactionType $type,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($professional, $amount, $currency, $type, $idempotencyKey, $referenceType, $referenceId, $description): WalletTransaction {
            $wallet = Wallet::query()
                ->where('professional_id', $professional->getKey())
                ->where('currency', strtoupper($currency))
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                ProfessionalProfile::query()->lockForUpdate()->findOrFail($professional->getKey());

                $wallet = Wallet::query()
                    ->where('professional_id', $professional->getKey())
                    ->where('currency', strtoupper($currency))
                    ->lockForUpdate()
                    ->first();
            }

            if ($wallet === null) {
                $wallet = Wallet::query()->forceCreate([
                    'professional_id' => $professional->getKey(),
                    'currency' => strtoupper($currency),
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'locked_balance' => 0,
                    'status' => 'active',
                ]);
            }

            if ($idempotencyKey !== null) {
                $existing = WalletTransaction::query()
                    ->where('wallet_id', $wallet->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if (
                        $this->minor((string) $existing->amount) !== $this->minor($amount)
                        || $existing->currency !== strtoupper($currency)
                        || $existing->type !== $type
                        || $existing->direction !== WalletTransactionDirection::CREDIT
                        || $existing->reference_type !== $referenceType
                        || $existing->reference_id !== $referenceId
                    ) {
                        throw new PaymentConflictException('La clé d’idempotence du crédit est déjà utilisée pour une autre opération.');
                    }

                    return $existing;
                }
            }

            $before = $this->minor((string) $wallet->pending_balance);
            $credit = $this->minor($amount);
            $after = $before + $credit;

            if ($credit <= 0) {
                throw ValidationException::withMessages(['amount' => 'Le montant doit être strictement positif.']);
            }

            $wallet->forceFill(['pending_balance' => $this->formatMinor($after)])->save();

            return WalletTransaction::query()->forceCreate([
                'wallet_id' => $wallet->getKey(),
                'type' => $type,
                'direction' => WalletTransactionDirection::CREDIT,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'balance_before' => $this->formatMinor($before),
                'balance_after' => $this->formatMinor($after),
                'balance_bucket' => 'pending',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
            ]);
        }, attempts: 3);
    }

    public function releasePending(
        ProfessionalProfile $professional,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($professional, $amount, $currency, $idempotencyKey, $referenceType, $referenceId): WalletTransaction {
            $wallet = Wallet::query()
                ->where('professional_id', $professional->getKey())
                ->where('currency', strtoupper($currency))
                ->lockForUpdate()
                ->firstOrFail();

            $fingerprint = hash('sha256', $idempotencyKey);
            $pendingKey = 'wallet-release:'.$fingerprint.':pending';
            $availableKey = 'wallet-release:'.$fingerprint.':available';

            $existing = WalletTransaction::query()
                ->where('wallet_id', $wallet->getKey())
                ->whereIn('idempotency_key', [$idempotencyKey, $pendingKey, $availableKey])
                ->lockForUpdate()
                ->get();

            $existingAvailable = $existing->firstWhere('idempotency_key', $availableKey);
            $legacy = $existing->firstWhere('idempotency_key', $idempotencyKey);

            if ($existingAvailable !== null) {
                if (
                    $this->minor((string) $existingAvailable->amount) !== $this->minor($amount)
                    || $existingAvailable->currency !== strtoupper($currency)
                    || $existingAvailable->direction !== WalletTransactionDirection::CREDIT
                    || $existingAvailable->reference_type !== $referenceType
                    || $existingAvailable->reference_id !== $referenceId
                ) {
                    throw new PaymentConflictException('La clé d’idempotence de libération est déjà utilisée pour une autre opération.');
                }

                return $existingAvailable;
            }

            if ($legacy !== null) {
                if (
                    $this->minor((string) $legacy->amount) !== $this->minor($amount)
                    || $legacy->currency !== strtoupper($currency)
                    || $legacy->direction !== WalletTransactionDirection::CREDIT
                    || $legacy->reference_type !== $referenceType
                    || $legacy->reference_id !== $referenceId
                ) {
                    throw new PaymentConflictException('La clé d’idempotence de libération est déjà utilisée pour une autre opération.');
                }

                return $legacy;
            }

            if ($existing->isNotEmpty()) {
                throw new PaymentConflictException('Le registre de libération du solde est incomplet; un rapprochement est requis.');
            }

            $pending = $this->minor((string) $wallet->pending_balance);
            $available = $this->minor((string) $wallet->available_balance);
            $value = $this->minor($amount);

            if ($value <= 0 || $value > $pending) {
                throw new PaymentConflictException('Le solde en attente est insuffisant pour cette libération.');
            }

            $pendingAfter = $pending - $value;
            $availableAfter = $available + $value;

            $wallet->forceFill([
                'pending_balance' => $this->formatMinor($pendingAfter),
                'available_balance' => $this->formatMinor($availableAfter),
            ])->save();

            WalletTransaction::query()->forceCreate([
                'wallet_id' => $wallet->getKey(),
                'type' => WalletTransactionType::EARNING,
                'direction' => WalletTransactionDirection::DEBIT,
                'currency' => strtoupper($currency),
                'amount' => $this->formatMinor($value),
                'balance_before' => $this->formatMinor($pending),
                'balance_after' => $this->formatMinor($pendingAfter),
                'balance_bucket' => 'pending',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $pendingKey,
                'description' => 'Libération du solde professionnel — diminution du compartiment en attente.',
            ]);

            return WalletTransaction::query()->forceCreate([
                'wallet_id' => $wallet->getKey(),
                'type' => WalletTransactionType::EARNING,
                'direction' => WalletTransactionDirection::CREDIT,
                'currency' => strtoupper($currency),
                'amount' => $this->formatMinor($value),
                'balance_before' => $this->formatMinor($available),
                'balance_after' => $this->formatMinor($availableAfter),
                'balance_bucket' => 'available',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $availableKey,
                'description' => 'Libération du solde professionnel — augmentation du compartiment disponible.',
            ]);
        }, attempts: 3);
    }

    private function minor(string $value): int
    {
        $value = trim($value);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Montant financier invalide.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '0');
        $normalizedWhole = ltrim($whole, '0') ?: '0';

        if (strlen($normalizedWhole) > 16) {
            throw new \InvalidArgumentException('Montant financier hors limites techniques.');
        }

        $minor = ((int) $normalizedWhole * 100) + (int) str_pad($decimal, 2, '0');

        return $minor;
    }

    private function formatMinor(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Un solde ne peut pas être négatif.');
        }

        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
}
