<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly DatabaseManager $database,
    ) {}

    public function postForPayment(Payment $payment, PaymentTransaction $transaction): Commission
    {
        return $this->database->transaction(function () use ($payment, $transaction): Commission {
            $lockedPayment = Payment::query()
                ->with('order.professional')
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            $existing = Commission::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($lockedPayment->status !== PaymentStatus::SUCCEEDED) {
                throw ValidationException::withMessages([
                    'payment' => 'Une commission ne peut être comptabilisée que pour un paiement confirmé.',
                ]);
            }

            $order = $lockedPayment->order;
            $professional = $order?->professional;

            if ($order === null || $professional === null) {
                throw ValidationException::withMessages([
                    'order' => 'La commande doit avoir un professionnel cible pour calculer la commission.',
                ]);
            }

            if ($transaction->payment_id !== $lockedPayment->getKey()) {
                throw ValidationException::withMessages([
                    'transaction' => 'La transaction de paiement ne correspond pas au paiement.',
                ]);
            }

            $gross = $this->minor((string) $lockedPayment->amount);
            $rate = $this->rateMinor((string) config('commission.rate', '10.00'));

            if ($rate <= 0 || $rate >= 10000) {
                throw ValidationException::withMessages([
                    'commission_rate' => 'Le taux de commission doit être supérieur à 0 % et inférieur à 100 %.',
                ]);
            }

            $commission = intdiv(($gross * $rate) + 5000, 10000);
            $net = $gross - $commission;

            if ($commission < 0 || $net < 0 || $gross !== $commission + $net) {
                throw ValidationException::withMessages([
                    'commission' => 'Le calcul financier de la commission est invalide.',
                ]);
            }

            $record = Commission::query()->forceCreate([
                'order_id' => $order->getKey(),
                'payment_id' => $lockedPayment->getKey(),
                'professional_id' => $professional->getKey(),
                'gross_amount' => $this->formatMinor($gross),
                'commission_rate' => number_format($rate / 100, 2, '.', ''),
                'commission_amount' => $this->formatMinor($commission),
                'net_amount' => $this->formatMinor($net),
                'currency' => strtoupper($lockedPayment->currency),
                'calculation_type' => 'percentage',
                'status' => CommissionStatus::POSTED,
                'posted_at' => now(),
            ]);

            if ($net > 0) {
                $this->walletService->creditPending(
                    professional: $professional,
                    amount: $this->formatMinor($net),
                    currency: strtoupper($lockedPayment->currency),
                    type: WalletTransactionType::EARNING,
                    idempotencyKey: 'commission:'.$record->getKey().':earning',
                    referenceType: Commission::class,
                    referenceId: $record->getKey(),
                    description: 'Revenu professionnel net après commission PROXIWORK.',
                );
            }

            return $record;
        }, attempts: 3);
    }

    private function minor(string $value): int
    {
        $value = trim($value);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Montant financier invalide.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function rateMinor(string $value): int
    {
        $value = trim($value);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Taux de commission invalide.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function formatMinor(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }
}
