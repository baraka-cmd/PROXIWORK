<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Enums\CommissionStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Commission;
use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    public function get(CarbonInterface $from, CarbonInterface $to): array
    {
        $labels = $this->labels($from, $to);

        $activity = [
            'users' => $this->align($labels, $this->dailyCount(User::class, 'created_at', $from, $to)),
            'professionals' => $this->align($labels, $this->dailyCount(ProfessionalProfile::class, 'created_at', $from, $to)),
            'services' => $this->align($labels, $this->dailyCount(Service::class, 'created_at', $from, $to)),
            'requests' => $this->align($labels, $this->dailyCount(ServiceRequest::class, 'created_at', $from, $to, ['status' => ServiceRequestStatus::DRAFT->value], true)),
            'orders' => $this->align($labels, $this->dailyCount(Order::class, 'created_at', $from, $to)),
            'support_created' => $this->align($labels, $this->dailyCount(SupportTicket::class, 'created_at', $from, $to)),
            'support_resolved' => $this->align($labels, $this->dailyCount(SupportTicket::class, 'resolved_at', $from, $to)),
            'successful_payments' => $this->align($labels, $this->dailyCount(
                PaymentTransaction::class,
                'processed_at',
                $from,
                $to,
                ['status' => PaymentTransactionStatus::SUCCEEDED->value],
            )),
        ];

        $commercial = $this->commercial($from, $to);
        $catalog = $this->catalog($from, $to);
        $geography = $this->geography($from, $to);
        $financial = [
            'payment_volume' => $this->alignCurrencies($labels, $this->dailySumByCurrency(
                PaymentTransaction::class,
                'processed_at',
                'amount',
                $from,
                $to,
                ['status' => PaymentTransactionStatus::SUCCEEDED->value],
            )),
            'commissions' => $this->alignCurrencies($labels, $this->dailySumByCurrency(
                Commission::class,
                'posted_at',
                'commission_amount',
                $from,
                $to,
                ['status' => CommissionStatus::POSTED->value],
            )),
            'refunds' => $this->refundTotals($from, $to),
        ];

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'days' => count($labels),
            ],
            'labels' => $labels,
            'activity' => $activity,
            'commercial' => $commercial,
            'catalog' => $catalog,
            'geography' => $geography,
            'financial' => $financial,
            'totals' => array_map(static fn (array $values): int => array_sum($values), $activity),
        ];
    }

    private function commercial(CarbonInterface $from, CarbonInterface $to): array
    {
        $requests = ServiceRequest::query()
            ->where('status', '<>', ServiceRequestStatus::DRAFT->value)
            ->whereBetween('created_at', [$from, $to]);

        $requestCount = (clone $requests)->count();
        $requestsWithQuotation = (clone $requests)->whereHas('quotation')->count();
        $requestsWithOrder = (clone $requests)->whereHas('order')->count();

        $quotations = Quotation::query()->whereBetween('created_at', [$from, $to]);
        $quotationCount = (clone $quotations)->count();
        $acceptedQuotations = (clone $quotations)->where('status', QuotationStatus::ACCEPTED->value)->count();

        $orders = Order::query()->whereBetween('created_at', [$from, $to]);
        $orderCount = (clone $orders)->count();
        $completedOrders = Order::query()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $averageCompletionHours = Order::query()
            ->whereNotNull('accepted_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, accepted_at, completed_at)) AS average_hours')
            ->value('average_hours');

        return [
            'requests' => [
                'total' => $requestCount,
                'with_quotation' => $requestsWithQuotation,
                'with_order' => $requestsWithOrder,
                'by_status' => $this->statusCounts(ServiceRequest::class, $from, $to, true),
            ],
            'quotations' => [
                'total' => $quotationCount,
                'accepted' => $acceptedQuotations,
                'by_status' => $this->statusCounts(Quotation::class, $from, $to),
            ],
            'orders' => [
                'total' => $orderCount,
                'completed_in_period' => $completedOrders,
                'by_status' => $this->statusCounts(Order::class, $from, $to),
                'average_completion_hours' => $averageCompletionHours === null ? null : round((float) $averageCompletionHours, 1),
            ],
            'conversion' => [
                'request_to_quotation_percent' => $this->rate($requestsWithQuotation, $requestCount),
                'request_to_order_percent' => $this->rate($requestsWithOrder, $requestCount),
                'quotation_acceptance_percent' => $this->rate($acceptedQuotations, $quotationCount),
                'order_completion_percent' => $this->rate($completedOrders, $orderCount),
            ],
            'methodology' => 'Les demandes sont comptées selon leur date de création, les devis selon leur date de création et les commandes terminées selon completed_at. Ces taux descriptifs ne constituent pas une analyse de cohorte mature ni une attribution causale.',
        ];
    }

    private function catalog(CarbonInterface $from, CarbonInterface $to): array
    {
        $publishedServices = Service::query()->published()->count();
        $requestableServices = Service::query()->requestable()->count();
        $visibleProfessionals = ProfessionalProfile::query()
            ->publiclyVisible()
            ->whereHas('services', fn ($query) => $query->requestable())
            ->count();

        $rows = DB::table('categories')
            ->leftJoin('services', 'services.category_id', '=', 'categories.id')
            ->leftJoin('service_requests', function ($join) use ($from, $to): void {
                $join->on('service_requests.service_id', '=', 'services.id')
                    ->where('service_requests.status', '<>', ServiceRequestStatus::DRAFT->value)
                    ->whereBetween('service_requests.created_at', [$from, $to]);
            })
            ->leftJoin('orders', function ($join) use ($from, $to): void {
                $join->on('orders.service_request_id', '=', 'service_requests.id')
                    ->whereBetween('orders.created_at', [$from, $to]);
            })
            ->select(
                'categories.id',
                'categories.name',
                'categories.parent_id',
                DB::raw('COUNT(DISTINCT services.id) AS services_count'),
                DB::raw('COUNT(DISTINCT service_requests.id) AS requests_count'),
                DB::raw('COUNT(DISTINCT orders.id) AS orders_count'),
            )
            ->groupBy('categories.id', 'categories.name', 'categories.parent_id')
            ->orderByDesc('requests_count')
            ->orderBy('categories.name')
            ->limit(30)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'parent_id' => $row->parent_id === null ? null : (int) $row->parent_id,
                'services' => (int) $row->services_count,
                'requests' => (int) $row->requests_count,
                'orders' => (int) $row->orders_count,
            ])->all();

        return [
            'published_services' => $publishedServices,
            'requestable_services' => $requestableServices,
            'public_professionals_with_requestable_services' => $visibleProfessionals,
            'categories' => $rows,
            'views_tracking_available' => false,
            'methodology' => 'Les consultations de pages ne sont pas estimées : aucune télémétrie de vues fiable n’est présumée. Les chiffres par catégorie sont directs et ne consolident pas automatiquement les sous-catégories.',
        ];
    }

    private function geography(CarbonInterface $from, CarbonInterface $to): array
    {
        // Only public professional base locations are used. Client addresses,
        // address snapshots and precise GPS coordinates are deliberately excluded.
        $rows = ProfessionalProfile::query()
            ->publiclyVisible()
            ->leftJoin('service_requests', function ($join) use ($from, $to): void {
                $join->on('service_requests.professional_id', '=', 'professional_profiles.id')
                    ->where('service_requests.status', '<>', ServiceRequestStatus::DRAFT->value)
                    ->whereBetween('service_requests.created_at', [$from, $to]);
            })
            ->leftJoin('orders', function ($join) use ($from, $to): void {
                $join->on('orders.professional_id', '=', 'professional_profiles.id')
                    ->whereBetween('orders.created_at', [$from, $to]);
            })
            ->whereNotNull('professional_profiles.city')
            ->select(
                'professional_profiles.province',
                'professional_profiles.city',
                DB::raw('COUNT(DISTINCT professional_profiles.id) AS professionals_count'),
                DB::raw('COUNT(DISTINCT service_requests.id) AS requests_count'),
                DB::raw('COUNT(DISTINCT orders.id) AS orders_count'),
            )
            ->groupBy('professional_profiles.province', 'professional_profiles.city')
            ->orderByDesc('professionals_count')
            ->limit(50)
            ->get()
            ->map(function ($row): array {
                $count = (int) $row->professionals_count;

                return [
                    'province' => (string) ($row->province ?: 'Non renseignée'),
                    'city' => (string) $row->city,
                    'professionals' => $count >= 3 ? $count : null,
                    'requests' => (int) $row->requests_count,
                    'orders' => (int) $row->orders_count,
                    'suppressed' => $count < 3,
                ];
            })->all();

        return [
            'professional_base_locations' => $rows,
            'location_basis' => 'Localisation de base déclarée par les professionnels, et non adresse privée du client ou lieu exact d’exécution.',
            'privacy_threshold' => 3,
        ];
    }

    private function refundTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        return PaymentRefund::query()
            ->where('status', PaymentRefundStatus::SUCCEEDED->value)
            ->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$from, $to])
            ->select('currency', DB::raw('SUM(amount) AS total'), DB::raw('COUNT(*) AS operations_count'))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->currency => [
                'amount' => (float) $row->total,
                'count' => (int) $row->operations_count,
            ]])
            ->all();
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }

    private function statusCounts(string $model, CarbonInterface $from, CarbonInterface $to, bool $excludeDraft = false): array
    {
        $query = $model::query()->whereBetween('created_at', [$from, $to]);

        if ($excludeDraft && $model === ServiceRequest::class) {
            $query->where('status', '<>', ServiceRequestStatus::DRAFT->value);
        }

        return $query->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();
    }

    private function labels(CarbonInterface $from, CarbonInterface $to): array
    {
        $labels = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $labels;
    }

    private function dailyCount(
        string $model,
        string $dateColumn,
        CarbonInterface $from,
        CarbonInterface $to,
        array $where = [],
        bool $excludeWhere = false,
    ): array {
        $query = $model::query()
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$from, $to])
            ->selectRaw("DATE({$dateColumn}) AS bucket, COUNT(*) AS total")
            ->groupBy('bucket');

        foreach ($where as $column => $value) {
            $excludeWhere ? $query->where($column, '<>', $value) : $query->where($column, $value);
        }

        return $query->pluck('total', 'bucket')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();
    }

    private function dailySumByCurrency(
        string $model,
        string $dateColumn,
        string $amountColumn,
        CarbonInterface $from,
        CarbonInterface $to,
        array $where = [],
    ): array {
        $query = $model::query()
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$from, $to])
            ->selectRaw("DATE({$dateColumn}) AS bucket, currency, SUM({$amountColumn}) AS total")
            ->groupBy('bucket', 'currency');

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $result[(string) $row->currency][(string) $row->bucket] = (float) $row->total;
        }

        return $result;
    }

    private function align(array $labels, array $values): array
    {
        return array_map(static fn (string $label): int => (int) ($values[$label] ?? 0), $labels);
    }

    private function alignCurrencies(array $labels, array $values): array
    {
        $result = [];
        foreach ($values as $currency => $dailyValues) {
            $result[$currency] = array_map(static fn (string $label): float => (float) ($dailyValues[$label] ?? 0), $labels);
        }

        return $result;
    }
}
