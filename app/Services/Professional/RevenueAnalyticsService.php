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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAnalyticsService
{
    public function dashboard(ProfessionalProfile $professional, array $filters): array
    {
        [$start, $end, $periodLabel] = $this->period($filters);
        $base = $this->commissionQuery($professional, $filters, $start, $end, false, false);
        $posted = (clone $base)->where('commissions.status', CommissionStatus::POSTED->value);

        $summary = (clone $posted)
            ->selectRaw('commissions.currency, COUNT(commissions.id) as orders_count, SUM(commissions.gross_amount) as gross_amount, SUM(commissions.commission_amount) as commission_amount, SUM(commissions.net_amount) as net_amount')
            ->groupBy('commissions.currency')
            ->orderBy('commissions.currency')
            ->get();

        $completedOrders = $this->completedOrdersSummary($professional, $filters, $start, $end);
        $confirmedPayments = $this->confirmedPaymentsSummary($professional, $filters, $start, $end);
        $confirmedRefunds = $this->confirmedRefundsSummary($professional, $filters, $start, $end);
        $refundHistory = $this->confirmedRefundHistory($professional, $filters, $start, $end);
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
            ->merge($confirmedRefunds->pluck('currency'))
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
            'confirmedRefunds' => $confirmedRefunds,
            'refundHistory' => $refundHistory,
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

    public function periodDetails(array $filters): array
    {
        [$start, $end, $label] = $this->period($filters);

        return ['from' => $start, 'to' => $end, 'label' => $label];
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
        bool $includeReference = true,
    ): Builder {
        return Commission::query()
            ->where('commissions.professional_id', $professional->getKey())
            ->whereBetween('commissions.posted_at', [$start, $end])
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('commissions.currency', $currency))
            ->when($filters['order_id'] ?? null, fn (Builder $query, int $orderId) => $query->where('commissions.order_id', $orderId))
            ->when($filters['service_id'] ?? null, function (Builder $query, int $serviceId): void {
                $query->whereHas('order.items', fn (Builder $items) => $items->where('service_id', $serviceId));
            })
            ->when($includeStatus && ($filters['status'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('commissions.status', $filters['status']))
            ->when($includeReference && ($filters['reference'] ?? null) !== null, function (Builder $query) use ($filters): void {
                $reference = trim((string) $filters['reference']);

                if (preg_match('/^(?:COM-|#)?([0-9]+)$/i', $reference, $matches) === 1) {
                    $query->where('commissions.id', (int) $matches[1]);

                    return;
                }

                $query->whereHas('order', fn (Builder $order) => $order->where('order_number', $reference));
            });
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

    private function confirmedRefundsSummary(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return $this->confirmedRefundQuery($professional, $filters, $start, $end)
            ->selectRaw('payment_refunds.currency, COUNT(payment_refunds.id) as refunds_count, SUM(payment_refunds.amount) as refunded_amount')
            ->groupBy('payment_refunds.currency')
            ->orderBy('payment_refunds.currency')
            ->get();
    }

    private function confirmedRefundHistory(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ) {
        $query = $this->confirmedRefundQuery($professional, $filters, $start, $end)
            ->select([
                'payment_refunds.id',
                'payment_refunds.reference',
                'payment_refunds.amount',
                'payment_refunds.currency',
                'payment_refunds.processed_at',
                'payments.id as payment_id',
                'orders.id as order_id',
                'orders.order_number',
                'commissions.id as commission_id',
            ])
            ->orderByDesc('payment_refunds.processed_at')
            ->orderByDesc('payment_refunds.id');

        if (($filters['reference'] ?? null) !== null) {
            $reference = trim((string) $filters['reference']);

            $query->where(function ($nested) use ($reference): void {
                $nested->where('payment_refunds.reference', $reference)
                    ->orWhere('orders.order_number', $reference);

                if (preg_match('/^(?:COM-|#)?([0-9]+)$/i', $reference, $matches) === 1) {
                    $nested->orWhere('commissions.id', (int) $matches[1]);
                }
            });
        }

        return $query->paginate(10, ['*'], 'refund_page')->withQueryString();
    }

    private function confirmedRefundQuery(
        ProfessionalProfile $professional,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): QueryBuilder {
        return DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->join('commissions', 'commissions.payment_id', '=', 'payments.id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('commissions.professional_id', $professional->getKey())
            ->where('payment_refunds.status', 'succeeded')
            ->whereNotNull('payment_refunds.processed_at')
            ->whereBetween('payment_refunds.processed_at', [$start, $end])
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('payment_refunds.currency', $currency))
            ->when($filters['order_id'] ?? null, fn ($query, int $orderId) => $query->where('orders.id', $orderId))
            ->when($filters['service_id'] ?? null, fn ($query, int $serviceId) => $query->where(function ($nested) use ($serviceId): void {
                $nested->whereExists(function ($subquery) use ($serviceId): void {
                    $subquery->selectRaw('1')
                        ->from('service_requests')
                        ->whereColumn('service_requests.id', 'orders.service_request_id')
                        ->where('service_requests.service_id', $serviceId);
                })->orWhereExists(function ($subquery) use ($serviceId): void {
                    $subquery->selectRaw('1')
                        ->from('order_items')
                        ->whereColumn('order_items.order_id', 'orders.id')
                        ->where('order_items.service_id', $serviceId);
                });
            }));
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
        if ($driver === 'sqlite') {
            $format = match ($granularity) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-W%W',
                default => '%Y-%m',
            };
        } else {
            $format = match ($granularity) {
                'day' => '%Y-%m-%d',
                'week' => '%x-W%v',
                default => '%Y-%m',
            };
        }

        $keyFormat = match ($granularity) {
            'day' => 'Y-m-d',
            'week' => $driver === 'sqlite' ? 'Y-\\WW' : 'o-\\WW',
            default => 'Y-m',
        };
        $periodExpression = $driver === 'sqlite'
            ? "strftime('{$format}', commissions.posted_at)"
            : "DATE_FORMAT(commissions.posted_at, '{$format}')";

        $rows = $this->commissionQuery($professional, $filters, $start, $end, false, false)
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
        $items = DB::raw('(SELECT order_id, COUNT(DISTINCT service_id) AS service_count, CASE WHEN COUNT(DISTINCT service_id) = 1 THEN MIN(service_title) ELSE NULL END AS service_title, CASE WHEN COUNT(DISTINCT service_id) = 1 THEN MIN(service_id) ELSE NULL END AS service_id FROM order_items GROUP BY order_id) AS revenue_items');

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
                $query->where('service_requests.service_id', $serviceId)
                    ->orWhereExists(function ($subquery) use ($serviceId): void {
                        $subquery->selectRaw('1')
                            ->from('order_items as filtered_items')
                            ->whereColumn('filtered_items.order_id', 'orders.id')
                            ->where('filtered_items.service_id', $serviceId);
                    });
            }))
            ->selectRaw("CASE WHEN COALESCE(revenue_items.service_count, 0) > 1 THEN NULL ELSE COALESCE(services.id, revenue_items.service_id) END AS service_id, CASE WHEN COALESCE(revenue_items.service_count, 0) > 1 THEN 'Commande multi-services — ventilation indisponible' ELSE COALESCE(services.title, revenue_items.service_title, 'Service indisponible') END AS service_title, commissions.currency, SUM(commissions.net_amount) AS net_amount, COUNT(DISTINCT commissions.order_id) AS orders_count")
            ->groupBy('services.id', 'services.title', 'revenue_items.service_id', 'revenue_items.service_title', 'revenue_items.service_count', 'commissions.currency')
            ->orderByDesc('net_amount')
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
