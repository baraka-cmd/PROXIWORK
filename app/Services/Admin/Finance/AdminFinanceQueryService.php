<?php

declare(strict_types=1);

namespace App\Services\Admin\Finance;

use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminFinanceQueryService
{
    /**
     * Read-only financial overview. Amounts are always grouped by currency:
     * the dashboard must never add USD, CDF, EUR, or other currencies together.
     */
    public function overview(array $filters): array
    {
        [$from, $to] = $this->period($filters);

        $paymentStatuses = Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $transactionStatuses = PaymentTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $confirmedPayments = $this->sumByCurrency(
            Payment::query()
                ->where('status', PaymentStatus::SUCCEEDED->value)
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$from, $to]),
            'amount',
        );

        $commissions = Commission::query()
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$from, $to])
            ->select(
                'currency',
                DB::raw('SUM(commission_amount) AS commission_total'),
                DB::raw('SUM(net_amount) AS professional_total'),
                DB::raw('COUNT(*) AS operations_count'),
            )
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->currency => [
                'commission' => $this->decimal($row->commission_total),
                'professional_net' => $this->decimal($row->professional_total),
                'count' => (int) $row->operations_count,
            ]])
            ->all();

        // Wallet balances are point-in-time balances, not period flows.
        $wallets = Wallet::query()
            ->select(
                'currency',
                DB::raw('COUNT(*) AS wallets_count'),
                DB::raw('SUM(available_balance) AS available_total'),
                DB::raw('SUM(pending_balance) AS pending_total'),
                DB::raw('SUM(locked_balance) AS locked_total'),
            )
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->currency => [
                'count' => (int) $row->wallets_count,
                'available' => $this->decimal($row->available_total),
                'pending' => $this->decimal($row->pending_total),
                'locked' => $this->decimal($row->locked_total),
            ]])
            ->all();

        $missingCommissionCount = Payment::query()
            ->where('status', PaymentStatus::SUCCEEDED->value)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->whereDoesntHave('commission')
            ->count();

        $unknownTransactions = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::UNKNOWN->value)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $recentTransactions = PaymentTransaction::query()
            ->with([
                'payment:id,order_id,client_id',
                'payment.order:id,order_number,professional_id',
                'payment.order.professional:id,user_id',
                'payment.order.professional.user:id,name',
                'payment.client:id,name',
            ])
            ->when($from !== null && $to !== null, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to]))
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'payment_statuses' => $paymentStatuses,
            'transaction_statuses' => $transactionStatuses,
            'confirmed_payments' => $confirmedPayments,
            'commissions' => $commissions,
            'wallets' => $wallets,
            'charts' => $this->trendCharts($from, $to),
            'alerts' => [
                'unknown_transactions' => $unknownTransactions,
                'payments_missing_commission' => $missingCommissionCount,
            ],
            'recent_transactions' => $recentTransactions,
            'updated_at' => now(),
        ];
    }

    public function transactions(array $filters): LengthAwarePaginator
    {
        [$from, $to] = $this->optionalPeriod($filters);

        return PaymentTransaction::query()
            ->with([
                'payment:id,order_id,client_id,amount,currency,status',
                'payment.order:id,order_number,professional_id',
                'payment.order.professional:id,user_id',
                'payment.order.professional.user:id,name',
                'payment.client:id,name,email',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search)
                            ->orWhere('payment_id', (int) $search);
                    }

                    $nested->orWhere('provider_transaction_id', 'like', "%{$search}%")
                        ->orWhere('provider_reference', 'like', "%{$search}%")
                        ->orWhereHas('payment.order', fn (Builder $order) => $order->where('order_number', 'like', "%{$search}%"))
                        ->orWhereHas('payment.client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('payment.order.professional.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('provider', $provider))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($from !== null && $to !== null, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to]))
            ->latest('created_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function transaction(PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->load([
            'payment.order.professional.user',
            'payment.client',
        ]);

        $transaction->setRelation('adminEvents', PaymentEvent::query()
            ->where('payment_transaction_id', $transaction->getKey())
            ->select(['id', 'payment_id', 'payment_transaction_id', 'event_type', 'from_status', 'to_status', 'source', 'occurred_at'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get());

        return $transaction;
    }

    public function commissions(array $filters): LengthAwarePaginator
    {
        [$from, $to] = $this->optionalPeriod($filters);

        return Commission::query()
            ->with([
                'order:id,order_number,client_id,professional_id',
                'order.client:id,name',
                'professional:id,user_id',
                'professional.user:id,name,email',
                'payment:id,status,paid_at',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search)->orWhere('order_id', (int) $search);
                    }

                    $nested->orWhereHas('order', fn (Builder $order) => $order->where('order_number', 'like', "%{$search}%"))
                        ->orWhereHas('professional.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($from !== null && $to !== null, fn (Builder $query) => $query->whereBetween('posted_at', [$from, $to]))
            ->latest('posted_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function commission(Commission $commission): Commission
    {
        $commission->load([
            'order.client',
            'order.professional.user',
            'payment',
            'professional.user',
        ]);

        $commission->setRelation('walletEntries', WalletTransaction::query()
            ->where('reference_type', Commission::class)
            ->where('reference_id', $commission->getKey())
            ->select(['id', 'wallet_id', 'type', 'direction', 'currency', 'amount', 'balance_before', 'balance_after', 'balance_bucket', 'created_at'])
            ->orderBy('created_at')
            ->get());

        return $commission;
    }

    public function wallets(array $filters): LengthAwarePaginator
    {
        return Wallet::query()
            ->with(['professional:id,user_id', 'professional.user:id,name,email'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search);
                    }

                    $nested->orWhereHas('professional.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($filters['wallet_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function wallet(Wallet $wallet): Wallet
    {
        $wallet->load(['professional.user']);

        // This is deliberately a short contextual preview. The full immutable
        // ledger belongs to the dedicated financial-movements phase.
        $wallet->setRelation('recentTransactions', $wallet->transactions()
            ->select([
                'id', 'wallet_id', 'type', 'direction', 'currency', 'amount',
                'balance_before', 'balance_after', 'balance_bucket',
                'reference_type', 'reference_id', 'description', 'created_at',
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get());

        return $wallet;
    }

    /**
     * Lists remain complete by default; a date window is applied only when
     * the administrator explicitly supplies at least one boundary.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function optionalPeriod(array $filters): array
    {
        if (! isset($filters['from']) && ! isset($filters['to'])) {
            return [null, null];
        }

        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])->startOfDay()
            : CarbonImmutable::parse($filters['to'])->startOfDay();

        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return [$from, $to];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
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

    private function trendCharts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Keep the chart bounded even when an administrator selects a long
        // reporting period. The KPI totals above still use the full period.
        $chartFrom = $to->startOfDay()->subDays(89);
        if ($chartFrom->lt($from->startOfDay())) {
            $chartFrom = $from->startOfDay();
        }

        $paymentRows = Payment::query()
            ->where('status', PaymentStatus::SUCCEEDED->value)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$chartFrom, $to])
            ->selectRaw('DATE(paid_at) AS bucket, currency, SUM(amount) AS total')
            ->groupBy('bucket', 'currency')
            ->get();

        $commissionRows = Commission::query()
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$chartFrom, $to])
            ->selectRaw('DATE(posted_at) AS bucket, currency, SUM(commission_amount) AS total')
            ->groupBy('bucket', 'currency')
            ->get();

        $paymentValues = [];
        $commissionValues = [];
        $currencies = [];

        foreach ($paymentRows as $row) {
            $currency = (string) $row->currency;
            $currencies[$currency] = true;
            $paymentValues[$currency][(string) $row->bucket] = (float) $row->total;
        }

        foreach ($commissionRows as $row) {
            $currency = (string) $row->currency;
            $currencies[$currency] = true;
            $commissionValues[$currency][(string) $row->bucket] = (float) $row->total;
        }

        $labels = [];
        $dateKeys = [];
        $cursor = $chartFrom;
        $lastDay = $to->startOfDay();

        while ($cursor->lte($lastDay)) {
            $dateKeys[] = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $cursor = $cursor->addDay();
        }

        $series = [];
        foreach (array_keys($currencies) as $currency) {
            $series[$currency] = [
                'payments' => array_map(
                    static fn (string $date): float => (float) ($paymentValues[$currency][$date] ?? 0),
                    $dateKeys,
                ),
                'commissions' => array_map(
                    static fn (string $date): float => (float) ($commissionValues[$currency][$date] ?? 0),
                    $dateKeys,
                ),
            ];
        }

        return [
            'labels' => $labels,
            'currencies' => $series,
            'from' => $chartFrom->toDateString(),
            'to' => $lastDay->toDateString(),
            'is_limited' => $chartFrom->gt($from->startOfDay()),
        ];
    }

    private function sumByCurrency(Builder $query, string $column): array
    {
        return $query
            ->select('currency', DB::raw("SUM({$column}) AS total"))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->currency => $this->decimal($row->total)])
            ->all();
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
