<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Enums\CommissionStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Commission;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;

class AdminAnalyticsService
{
    public function get(CarbonInterface $from, CarbonInterface $to): array
    {
        $labels = $this->labels($from, $to);

        $activity = [
            'users' => $this->align($labels, $this->dailyCount(User::class, 'created_at', $from, $to)),
            'professionals' => $this->align($labels, $this->dailyCount(ProfessionalProfile::class, 'created_at', $from, $to)),
            'services' => $this->align($labels, $this->dailyCount(Service::class, 'created_at', $from, $to)),
            'requests' => $this->align($labels, $this->dailyCount(ServiceRequest::class, 'created_at', $from, $to)),
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

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'days' => count($labels),
            ],
            'labels' => $labels,
            'activity' => $activity,
            'financial' => [
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
            ],
            'totals' => array_map(
                static fn (array $values): int => array_sum($values),
                $activity,
            ),
        ];
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
    ): array {
        $query = $model::query()
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$from, $to])
            ->selectRaw("DATE({$dateColumn}) AS bucket, COUNT(*) AS total")
            ->groupBy('bucket');

        foreach ($where as $column => $value) {
            $query->where($column, $value);
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
        return array_map(
            static fn (string $label): int => (int) ($values[$label] ?? 0),
            $labels,
        );
    }

    private function alignCurrencies(array $labels, array $values): array
    {
        $result = [];

        foreach ($values as $currency => $dailyValues) {
            $result[$currency] = array_map(
                static fn (string $label): float => (float) ($dailyValues[$label] ?? 0),
                $labels,
            );
        }

        return $result;
    }
}
