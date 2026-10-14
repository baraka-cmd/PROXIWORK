<?php

declare(strict_types=1);

namespace App\Services\Professional;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Commission;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAnalyticsService
{
    public function dashboard(ProfessionalProfile $professional, array $filters): array
    {
        [$start, $end, $periodLabel] = $this->period($filters);
        $base = $this->commissionQuery($professional, $filters, $start, $end, false);
        $posted = (clone $base)->where('commissions.status', CommissionStatus::POSTED->value);

        $summary = (clone $posted)
            ->selectRaw('commissions.currency, COUNT(commissions.id) as orders_count, SUM(commissions.gross_amount) as gross_amount, SUM(commissions.commission_amount) as commission_amount, SUM(commissions.net_amount) as net_amount')
            ->groupBy('commissions.currency')
            ->orderBy('commissions.currency')
            ->get();

        $completedOrders = $this->completedOrdersSummary($professional, $filters, $start, $end);
        $confirmedPayments = $this->confirmedPaymentsSummary($professional, $filters, $start, $end);
        $pendingBalances = Wallet::query()
            ->where('professional_id', $professional->getKey())
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->selectRaw('currency, pending_balance')
            ->orderBy('currency')
            ->get();

        $commissions = $this->commissionQuery($professional, $filters, $start, $end, true)
            ->with(['order.items', 'payment'])
            ->orderByDesc('commissions.posted_at')
            ->orderByDesc('commissions.id')
            ->paginate(15)
            ->withQueryString();

        $currencies = Commission::query()
            ->where('professional_id', $professional->getKey())
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->distinct()
            ->pluck('currency')
            ->merge(Wallet::query()
                ->where('professional_id', $professional->getKey())
                ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
                ->distinct()
                ->pluck('currency'))
            ->merge($completedOrders->pluck('currency'))
            ->merge($confirmedPayments->pluck('currency'))
            ->when(($filters['currency'] ?? null) !== null, fn (Collection $values) => $values->push($filters['currency']))
            ->unique()
            ->sort()
            ->values();

        return [
            'period' => [
                'key' => $filters['period'] ?? '30d',
                'label' => $periodLabel,
                'from' => $start,
                'to' => $end,
            ],
            'summary' => $summary,
            'completedOrders' => $completedOrders,
            'confirmedPayments' => $confirmedPayments,
            'pendingBalances' => $pendingBalances,
            'commissions' => $commissions,
            'currencies' => $currencies,
            'services' => $professional->services()->orderBy('title')->get(['id', 'title']),
            'evolution' => $this->evolution($professional, $filters, $start, $end, $currencies),
            'serviceBreakdown' => $this->serviceBreakdown($professional, $filters, $start, $end),
            'lastUpdatedAt' => Commission::query()
                ->where('professional_id', $professional->getKey())
                ->max('updated_at'),
        ];
    }

    public function exportQuery(ProfessionalProfile $professional, array $filters): Builder
    {
        [$start, $end] = $this->period($filters);

        return $this->commissionQuery($professional, $filters, $start, $end)
            ->with(['order.items'])
            ->orderBy('commissions.posted_at')
            ->orderBy('commissions.id');
    }

    public function findForProfessional(ProfessionalProfile $professional, int $commissionId): Commission
    {
        return Commission::query()
            ->where('professional_id', $professional->getKey())
            ->with(['order.items', 'order.statusHistories', 'payment.transactions', 'payment.events'])
            ->findOrFail($commissionId);
    }

    private function commissionQuery(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $includeStatus = true,
    ): Builder {
        return Commission::query()
            ->where('commissions.professional_id', $professional->getKey())
            ->whereBetween('commissions.posted_at', [$start, $end])
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('commissions.currency', $currency))
            ->when($filters['order_id'] ?? null, fn (Builder $query, int $orderId) => $query->where('commissions.order_id', $orderId))
            ->when($filters['service_id'] ?? null, function (Builder $query, int $serviceId): void {
                $query->whereHas('order.items', fn (Builder $items) => $items->where('service_id', $serviceId));
            })
            ->when($includeStatus && ($filters['status'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('commissions.status', $filters['status']));
    }

    private function completedOrdersSummary(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return Order::query()
            ->where('professional_id', $professional->getKey())
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$start, $end])
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['order_id'] ?? null, fn (Builder $query, int $orderId) => $query->whereKey($orderId))
            ->when($filters['service_id'] ?? null, fn (Builder $query, int $serviceId) => $query->whereHas('items', fn (Builder $items) => $items->where('service_id', $serviceId)))
            ->selectRaw('currency, COUNT(id) as orders_count, SUM(total) as total_amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();
    }

    private function confirmedPaymentsSummary(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return DB::table('payments')
            ->join('commissions', 'commissions.payment_id', '=', 'payments.id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('commissions.professional_id', $professional->getKey())
            ->where('commissions.status', CommissionStatus::POSTED->value)
            ->where('payments.status', PaymentStatus::SUCCEEDED->value)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('payments.currency', $currency))
            ->when($filters['order_id'] ?? null, fn ($query, int $orderId) => $query->where('orders.id', $orderId))
            ->when($filters['service_id'] ?? null, fn ($query, int $serviceId) => $query->whereExists(function ($subquery) use ($serviceId): void {
                $subquery->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.service_id', $serviceId);
            }))
            ->selectRaw('payments.currency, COUNT(payments.id) as payments_count, SUM(payments.amount) as paid_amount')
            ->groupBy('payments.currency')
            ->orderBy('payments.currency')
            ->get();
    }

    private function evolution(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $currencies,
    ): array {
        $days = $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
        $granularity = $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');
        $driver = DB::connection()->getDriverName();
        $format = $driver === 'sqlite'
            ? match ($granularity) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-W%W',
                default => '%Y-%m',
            }
            : match ($granularity) {
                'day' => '%Y-%m-%d',
                'week' => '%x-W%v',
                default => '%Y-%m',
            };
        $keyFormat = match ($granularity) {
            'day' => 'Y-m-d',
            'week' => $driver === 'sqlite' ? 'Y-\\WW' : 'o-\\WW',
            default => 'Y-m',
        };
        $periodExpression = $driver === 'sqlite'
            ? "strftime('{$format}', commissions.posted_at)"
            : "DATE_FORMAT(commissions.posted_at, '{$format}')";

        $rows = $this->commissionQuery($professional, $filters, $start, $end, false)
            ->where('commissions.status', CommissionStatus::POSTED->value)
            ->selectRaw("commissions.currency, {$periodExpression} as period_key, SUM(commissions.net_amount) as net_amount")
            ->groupBy('commissions.currency', 'period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy(fn ($row) => $row->currency.'|'.$row->period_key);

        $series = [];
        foreach ($currencies as $currency) {
            $labels = [];
            $values = [];
            $cursor = match ($granularity) {
                'day' => $start->startOfDay(),
                'week' => $start->startOfWeek(),
                default => $start->startOfMonth(),
            };

            $last = match ($granularity) {
                'day' => $end->startOfDay(),
                'week' => $end->startOfWeek(),
                default => $end->startOfMonth(),
            };

            while ($cursor <= $last) {
                $key = $cursor->format($keyFormat);
                $labels[] = match ($granularity) {
                    'day' => $cursor->format('d/m'),
                    'week' => 'Sem. '.$cursor->format('W'),
                    default => $cursor->format('m/Y'),
                };
                $values[] = (float) ($rows->get($currency.'|'.$key)?->net_amount ?? 0);
                $cursor = match ($granularity) {
                    'day' => $cursor->addDay(),
                    'week' => $cursor->addWeek(),
                    default => $cursor->addMonth(),
                };
            }

            $series[] = [
                'currency' => $currency,
                'labels' => $labels,
                'values' => $values,
                'granularity' => $granularity,
            ];
        }

        return $series;
    }

    private function serviceBreakdown(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $items = DB::raw('(SELECT order_id, MIN(service_title) AS service_title, MIN(service_id) AS service_id FROM order_items GROUP BY order_id) AS revenue_items');

        return DB::table('commissions')
            ->join('orders', 'orders.id', '=', 'commissions.order_id')
            ->leftJoin('service_requests', 'service_requests.id', '=', 'orders.service_request_id')
            ->leftJoin('services', 'services.id', '=', 'service_requests.service_id')
            ->leftJoin($items, 'revenue_items.order_id', '=', 'orders.id')
            ->where('commissions.professional_id', $professional->getKey())
            ->where('commissions.status', CommissionStatus::POSTED->value)
            ->whereBetween('commissions.posted_at', [$start, $end])
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('commissions.currency', $currency))
            ->when($filters['order_id'] ?? null, fn ($query, int $orderId) => $query->where('orders.id', $orderId))
            ->when($filters['service_id'] ?? null, fn ($query, int $serviceId) => $query->where(function ($query) use ($serviceId): void {
                $query->where('service_requests.service_id', $serviceId)->orWhere('revenue_items.service_id', $serviceId);
            }))
            ->selectRaw("COALESCE(services.title, revenue_items.service_title, 'Service indisponible') AS service_title, commissions.currency, SUM(commissions.net_amount) AS net_amount, COUNT(DISTINCT commissions.order_id) AS orders_count")
            ->groupBy('services.id', 'services.title', 'revenue_items.service_title', 'commissions.currency')
            ->orderByDesc('net_amount')
            ->limit(10)
            ->get();
    }

    private function period(array $filters): array
    {
        $now = CarbonImmutable::now();
        $period = $filters['period'] ?? '30d';

        if ($period === 'custom') {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'])->startOfDay();
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'])->endOfDay();

            return [$start, $end, $start->format('d/m/Y').' – '.$end->format('d/m/Y')];
        }

        [$start, $label] = match ($period) {
            '7d' => [$now->startOfDay()->subDays(6), '7 derniers jours'],
            'this_month' => [$now->startOfMonth(), 'Ce mois'],
            'this_quarter' => [$now->startOfQuarter(), 'Ce trimestre'],
            'this_year' => [$now->startOfYear(), 'Cette année'],
            default => [$now->startOfDay()->subDays(29), '30 derniers jours'],
        };

        return [$start, $now->endOfDay(), $label];
    }
}
