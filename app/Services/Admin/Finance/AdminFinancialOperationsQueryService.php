<?php

declare(strict_types=1);

namespace App\Services\Admin\Finance;

use App\Enums\CommissionStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminFinancialOperationsQueryService
{
    public function movements(array $filters): LengthAwarePaginator
    {
        [$from, $to] = $this->optionalPeriod($filters);

        return WalletTransaction::query()
            ->select([
                'id', 'wallet_id', 'type', 'direction', 'currency', 'amount',
                'balance_before', 'balance_after', 'balance_bucket',
                'reference_type', 'reference_id', 'description', 'created_at',
            ])
            ->with(['wallet:id,professional_id,currency', 'wallet.professional:id,user_id', 'wallet.professional.user:id,name,email'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search)
                            ->orWhere('reference_id', (int) $search);
                    }
                    $nested->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('wallet.professional.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', strtoupper($currency)))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['direction'] ?? null, fn (Builder $q, string $direction) => $q->where('direction', $direction))
            ->when($filters['bucket'] ?? null, fn (Builder $q, string $bucket) => $q->where('balance_bucket', $bucket))
            ->when($from !== null, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('created_at', '<=', $to))
            ->latest('created_at')->latest('id')
            ->paginate($filters['per_page'] ?? 20)->withQueryString();
    }

    public function movement(WalletTransaction $movement): WalletTransaction
    {
        return WalletTransaction::query()
            ->select([
                'id', 'wallet_id', 'type', 'direction', 'currency', 'amount',
                'balance_before', 'balance_after', 'balance_bucket',
                'reference_type', 'reference_id', 'description', 'created_at',
            ])
            ->with(['wallet.professional.user'])
            ->findOrFail($movement->getKey());
    }

    public function reports(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $payments = Payment::query()
            ->where('status', PaymentStatus::SUCCEEDED->value)->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', strtoupper($currency)))
            ->when($filters['professional_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('order', fn (Builder $o) => $o->where('professional_id', $id)))
            ->selectRaw('currency, COUNT(*) as operations_count, SUM(amount) as amount_total')
            ->groupBy('currency')->get()->keyBy('currency');

        $refunds = PaymentRefund::query()
            ->where('status', PaymentRefundStatus::SUCCEEDED->value)->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$from, $to])
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', strtoupper($currency)))
            ->when($filters['professional_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('payment.order', fn (Builder $o) => $o->where('professional_id', $id)))
            ->selectRaw('currency, COUNT(*) as operations_count, SUM(amount) as amount_total')
            ->groupBy('currency')->get()->keyBy('currency');

        $commissions = Commission::query()
            ->where('status', CommissionStatus::POSTED->value)->whereBetween('posted_at', [$from, $to])
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', strtoupper($currency)))
            ->when($filters['professional_id'] ?? null, fn (Builder $q, int $id) => $q->where('professional_id', $id))
            ->selectRaw('currency, COUNT(*) as operations_count, SUM(commission_amount) as commission_total, SUM(net_amount) as professional_total')
            ->groupBy('currency')->get()->keyBy('currency');

        $withdrawals = Withdrawal::query()
            ->where('status', WithdrawalStatus::SUCCEEDED->value)->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', strtoupper($currency)))
            ->when($filters['professional_id'] ?? null, fn (Builder $q, int $id) => $q->where('professional_id', $id))
            ->selectRaw('currency, COUNT(*) as operations_count, SUM(amount) as amount_total')
            ->groupBy('currency')->get()->keyBy('currency');

        $wallets = Wallet::query()
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', strtoupper($currency)))
            ->when($filters['professional_id'] ?? null, fn (Builder $q, int $id) => $q->where('professional_id', $id))
            ->selectRaw('currency, COUNT(*) as wallet_count, SUM(available_balance) as available_total, SUM(pending_balance) as pending_total, SUM(locked_balance) as locked_total')
            ->groupBy('currency')->get()->keyBy('currency');

        $currencies = collect([$payments, $refunds, $commissions, $withdrawals, $wallets])
            ->flatMap(fn ($rows) => $rows->keys())->unique()->sort()->values();

        $byCurrency = $currencies->mapWithKeys(function (string $currency) use ($payments, $refunds, $commissions, $withdrawals, $wallets): array {
            $paid = $this->minor($payments[$currency]->amount_total ?? '0');
            $refunded = $this->minor($refunds[$currency]->amount_total ?? '0');
            return [$currency => [
                'payments' => $this->fromMinor($paid),
                'payment_count' => (int) ($payments[$currency]->operations_count ?? 0),
                'refunds' => $this->fromMinor($refunded),
                'refund_count' => (int) ($refunds[$currency]->operations_count ?? 0),
                'net_collected_after_refunds' => $this->fromMinor($paid - $refunded),
                'commission' => $this->money($commissions[$currency]->commission_total ?? 0),
                'professional_net' => $this->money($commissions[$currency]->professional_total ?? 0),
                'commission_count' => (int) ($commissions[$currency]->operations_count ?? 0),
                'withdrawals' => $this->money($withdrawals[$currency]->amount_total ?? 0),
                'withdrawal_count' => (int) ($withdrawals[$currency]->operations_count ?? 0),
                'wallet_count' => (int) ($wallets[$currency]->wallet_count ?? 0),
                'available_balance' => $this->money($wallets[$currency]->available_total ?? 0),
                'pending_balance' => $this->money($wallets[$currency]->pending_total ?? 0),
                'locked_balance' => $this->money($wallets[$currency]->locked_total ?? 0),
            ]];
        });

        return [
            'from' => $from,
            'to' => $to,
            'by_currency' => $byCurrency,
            'definitions' => [
                'payments' => 'Paiements confirmés selon paid_at.',
                'refunds' => 'Remboursements confirmés selon processed_at; une demande reçue n’est pas comptée.',
                'commission' => 'Commissions comptabilisées selon posted_at.',
                'withdrawals' => 'Retraits réussis selon completed_at.',
                'wallet_balances' => 'Photographie des soldes actuels, pas des flux de la période.',
            ],
        ];
    }

    public function exportRows(array $filters): \Generator
    {
        [$from, $to] = $this->period($filters);
        $currency = $filters['currency'] ?? null;
        $professionalId = $filters['professional_id'] ?? null;

        $payments = Payment::query()->where('status', PaymentStatus::SUCCEEDED->value)->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->when($currency, fn (Builder $q) => $q->where('currency', $currency))
            ->when($professionalId, fn (Builder $q) => $q->whereHas('order', fn (Builder $o) => $o->where('professional_id', $professionalId)))
            ->with(['client:id,name'])->orderBy('id');
        foreach ($payments->lazyById(500) as $payment) {
            yield [$payment->paid_at?->toDateTimeString(), 'Paiement confirmé', 'PAY-'.$payment->id, $payment->client?->name, $payment->amount, $payment->currency, $payment->status->value, 'PAY-'.$payment->id];
        }

        $refunds = PaymentRefund::query()->where('status', PaymentRefundStatus::SUCCEEDED->value)->whereBetween('processed_at', [$from, $to])
            ->when($currency, fn (Builder $q) => $q->where('currency', $currency))
            ->when($professionalId, fn (Builder $q) => $q->whereHas('payment.order', fn (Builder $o) => $o->where('professional_id', $professionalId)))
            ->with(['client:id,name'])->orderBy('id');
        foreach ($refunds->lazyById(500) as $refund) {
            yield [$refund->processed_at?->toDateTimeString(), 'Remboursement confirmé', $refund->reference, $refund->client?->name, $refund->amount, $refund->currency, $refund->status->value, 'PAY-'.$refund->payment_id];
        }

        $commissions = Commission::query()->where('status', CommissionStatus::POSTED->value)->whereBetween('posted_at', [$from, $to])
            ->when($currency, fn (Builder $q) => $q->where('currency', $currency))
            ->when($professionalId, fn (Builder $q) => $q->where('professional_id', $professionalId))
            ->with(['professional.user:id,name'])->orderBy('id');
        foreach ($commissions->lazyById(500) as $commission) {
            yield [$commission->posted_at?->toDateTimeString(), 'Commission comptabilisée', 'COM-'.$commission->id, $commission->professional?->user?->name, $commission->commission_amount, $commission->currency, $commission->status->value, 'ORDER-'.$commission->order_id];
        }

        $withdrawals = Withdrawal::query()->where('status', WithdrawalStatus::SUCCEEDED->value)->whereBetween('completed_at', [$from, $to])
            ->when($currency, fn (Builder $q) => $q->where('currency', $currency))
            ->when($professionalId, fn (Builder $q) => $q->where('professional_id', $professionalId))
            ->with(['professional.user:id,name'])->orderBy('id');
        foreach ($withdrawals->lazyById(500) as $withdrawal) {
            yield [$withdrawal->completed_at?->toDateTimeString(), 'Retrait confirmé', 'WD-'.$withdrawal->id, $withdrawal->professional?->user?->name, $withdrawal->amount, $withdrawal->currency, $withdrawal->status->value, $withdrawal->provider_transaction_id];
        }

        // The CSV also carries current wallet snapshots; they are not period flows.
        $wallets = Wallet::query()
            ->when($currency, fn (Builder $q) => $q->where('currency', $currency))
            ->when($professionalId, fn (Builder $q) => $q->where('professional_id', $professionalId))
            ->with(['professional.user:id,name'])
            ->orderBy('id');

        foreach ($wallets->lazyById(500) as $wallet) {
            $name = $wallet->professional?->user?->name;
            $reference = 'WALLET-'.$wallet->getKey();
            $snapshotAt = now()->toDateTimeString();

            yield [$snapshotAt, 'Solde disponible actuel', $reference, $name, $wallet->available_balance, $wallet->currency, 'instantané', $reference];
            yield [$snapshotAt, 'Solde en attente actuel', $reference, $name, $wallet->pending_balance, $wallet->currency, 'instantané', $reference];
            yield [$snapshotAt, 'Solde bloqué actuel', $reference, $name, $wallet->locked_balance, $wallet->currency, 'instantané', $reference];
        }
    }

    private function optionalPeriod(array $filters): array
    {
        if (! isset($filters['from']) && ! isset($filters['to'])) {
            return [null, null];
        }

        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to'])->endOfDay() : null;

        return [$from, $to];
    }

    private function period(array $filters): array
    {
        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])->startOfDay()
            : CarbonImmutable::now()->subDays(29)->startOfDay();
        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return [$from, $to];
    }

    private function money(mixed $value): string
    {
        $value = trim((string) ($value ?? '0'));
        if (preg_match('/^(\\d+)(?:\\.(\\d{1,2}))?$/', $value, $matches) !== 1) {
            return '0.00';
        }

        return $matches[1].'.'.str_pad($matches[2] ?? '', 2, '0');
    }

    private function minor(mixed $value): int
    {
        $value = trim((string) ($value ?? '0'));
        if (preg_match('/^(\\d+)(?:\\.(\\d{1,2}))?$/', $value, $matches) !== 1) {
            return 0;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function fromMinor(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
