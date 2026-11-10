<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Enums\CommissionStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
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
            'requests' => $this->align($labels, $this->dailyCount(
                ServiceRequest::class,
                'requested_at',
                $from,
                $to,
                ['status' => ServiceRequestStatus::DRAFT->value],
                true,
            )),
            'orders' => $this->align($labels, $this->dailyCount(Order::class, 'created_at', $from, $to)),
            'support_created' => $this->align($labels, $this->dailyCount(SupportTicket::class, 'created_at', $from, $to)),
            'support_resolved' => $this->align($labels, $this->dailyCount(SupportTicket::class, 'resolved_at', $from, $to)),
            'successful_payments' => $this->align($labels, $this->dailyCount(
                Payment::class,
                'paid_at',
                $from,
                $to,
                ['status' => PaymentStatus::SUCCEEDED->value],
            )),
        ];

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'days' => count($labels),
            ],
            'labels' => $labels,
            'activity' => $activity,
            'commercial' => $this->commercial($from, $to),
            'catalog' => $this->catalog($from, $to),
            'geography' => $this->geography($from, $to),
            'financial' => [
                // Payment is the canonical successful financial operation.
                // Transaction attempts are technical attempts and can be retried.
                'payment_volume' => $this->alignCurrencies($labels, $this->dailySumByCurrency(
                    Payment::class,
                    'paid_at',
                    'amount',
                    $from,
                    $to,
                    ['status' => PaymentStatus::SUCCEEDED->value],
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
            ],
            'totals' => array_map(static fn (array $values): int => array_sum($values), $activity),
        ];
    }

    private function commercial(CarbonInterface $from, CarbonInterface $to): array
    {
        // requested_at is set only when a draft is submitted to the professional.
        $requests = ServiceRequest::query()
            ->whereNotNull('requested_at')
            ->where('status', '<>', ServiceRequestStatus::DRAFT->value)
            ->whereBetween('requested_at', [$from, $to]);

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

        $durationExpression = match (DB::connection()->getDriverName()) {
            'mysql' => 'AVG(TIMESTAMPDIFF(SECOND, accepted_at, completed_at))',
            'sqlite' => 'AVG((julianday(completed_at) - julianday(accepted_at)) * 86400)',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (completed_at - accepted_at)))',
            default => null,
        };

        $averageCompletionSeconds = $durationExpression === null
            ? null
            : Order::query()
                ->whereNotNull('accepted_at')
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$from, $to])
                ->selectRaw($durationExpression.' AS average_seconds')
                ->value('average_seconds');

        $averageCompletionHours = $averageCompletionSeconds === null
            ? null
            : round((float) $averageCompletionSeconds / 3600, 1);

        return [
            'requests' => [
                'total' => $requestCount,
                'with_quotation' => $requestsWithQuotation,
                'with_order' => $requestsWithOrder,
                'by_status' => $this->statusCounts(ServiceRequest::class, $from, $to, true, 'requested_at'),
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
                'average_completion_hours' => $averageCompletionHours,
            ],
            'conversion' => [
                'request_to_quotation_percent' => $this->rate($requestsWithQuotation, $requestCount),
                'request_to_order_percent' => $this->rate($requestsWithOrder, $requestCount),
                'quotation_acceptance_percent' => $this->rate($acceptedQuotations, $quotationCount),
            ],
            'methodology' => 'Les demandes sont attribuées à la période par requested_at (envoi effectif, et non création du brouillon). Les devis et commandes sont attribués par leur date de création. Les commandes terminées sont comptées séparément par completed_at. Les taux décrivent les enregistrements de la période et ne constituent pas une cohorte mature ni une attribution causale.',
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

        // Current eligible supply is calculated separately from historical demand.
        $requestableByCategory = Service::query()
            ->requestable()
            ->select('category_id')
            ->selectRaw('COUNT(*) AS eligible_services_count')
            ->groupBy('category_id');

        // Request and order facts are joined independently through a unique request
        // relation and counted distinctly to prevent join multiplication.
        $rows = DB::table('categories')
            ->leftJoinSub($requestableByCategory, 'eligible_services', function ($join): void {
                $join->on('eligible_services.category_id', '=', 'categories.id');
            })
            ->leftJoin('services as requested_services', 'requested_services.category_id', '=', 'categories.id')
            ->leftJoin('service_requests', function ($join) use ($from, $to): void {
                $join->on('service_requests.service_id', '=', 'requested_services.id')
                    ->whereNotNull('service_requests.requested_at')
                    ->where('service_requests.status', '<>', ServiceRequestStatus::DRAFT->value)
                    ->whereBetween('service_requests.requested_at', [$from, $to]);
            })
            ->leftJoin('orders', function ($join) use ($from, $to): void {
                $join->on('orders.service_request_id', '=', 'service_requests.id')
                    ->whereBetween('orders.created_at', [$from, $to]);
            })
            ->select(
                'categories.id',
                'categories.name',
                'categories.parent_id',
                DB::raw('COALESCE(eligible_services.eligible_services_count, 0) AS services_count'),
                DB::raw('COUNT(DISTINCT service_requests.id) AS requests_count'),
                DB::raw('COUNT(DISTINCT orders.id) AS orders_count'),
            )
            ->groupBy('categories.id', 'categories.name', 'categories.parent_id', 'eligible_services.eligible_services_count')
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
            'methodology' => 'Le nombre de services par catégorie correspond à l’offre actuellement éligible à une nouvelle demande; demandes et commandes sont des événements de la période. Les catégories sont directes, sans consolidation automatique des sous-catégories. Les vues ne sont pas estimées faute de télémétrie fiable.',
        ];
    }

    private function geography(CarbonInterface $from, CarbonInterface $to): array
    {
        // Only public professional base locations are used. Client addresses,
        // address snapshots and precise GPS coordinates are deliberately excluded.
        $visibleProfessionalIds = ProfessionalProfile::query()
            ->publiclyVisible()
            ->select('professional_profiles.id');

        $grouped = DB::table('professional_profiles')
            ->whereIn('professional_profiles.id', $visibleProfessionalIds)
            ->leftJoin('service_requests', function ($join) use ($from, $to): void {
                $join->on('service_requests.professional_id', '=', 'professional_profiles.id')
                    ->whereNotNull('service_requests.requested_at')
                    ->where('service_requests.status', '<>', ServiceRequestStatus::DRAFT->value)
                    ->whereBetween('service_requests.requested_at', [$from, $to]);
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
            ->get();

        // Suppress the whole small group, not just its headcount: otherwise
        // requests/orders could reveal activity of a single professional.
        $suppressedGroups = $grouped->filter(fn ($row): bool => (int) $row->professionals_count < 3)->count();
        $rows = $grouped
            ->filter(fn ($row): bool => (int) $row->professionals_count >= 3)
            ->map(fn ($row): array => [
                'province' => (string) ($row->province ?: 'Non renseignée'),
                'city' => (string) $row->city,
                'professionals' => (int) $row->professionals_count,
                'requests' => (int) $row->requests_count,
                'orders' => (int) $row->orders_count,
            ])->values()->all();

        return [
            'professional_base_locations' => $rows,
            'suppressed_location_groups' => $suppressedGroups,
            'location_basis' => 'Localisation de base déclarée par les professionnels, et non adresse privée du client ou lieu exact d’exécution. Les groupes de moins de trois professionnels sont omis entièrement.',
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
                'amount' => round((float) $row->total, 2),
                'count' => (int) $row->operations_count,
            ]])
            ->all();
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }

    private function statusCounts(
        string $model,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $excludeDraft = false,
        string $dateColumn = 'created_at',
    ): array {
        $query = $model::query()->whereBetween($dateColumn, [$from, $to]);

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
            $result[(string) $row->currency][(string) $row->bucket] = round((float) $row->total, 2);
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
