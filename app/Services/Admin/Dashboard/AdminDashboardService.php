<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Enums\CommissionStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Category;
use App\Models\Commission;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function get(CarbonInterface $from, CarbonInterface $to): array
    {
        $usersTotal = User::query()->count();
        $usersNew = User::query()->whereBetween('created_at', [$from, $to])->count();

        $professionalsTotal = ProfessionalProfile::query()->count();
        $professionalsNew = ProfessionalProfile::query()->whereBetween('created_at', [$from, $to])->count();
        $verification = ProfessionalProfile::query()
            ->select('verification_status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();

        $clientsTotal = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'client'))->count();
        $clientsNew = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'client'))
            ->whereBetween('users.created_at', [$from, $to])
            ->count();

        $suspendedUsers = User::query()->where('account_status', 'suspended')->count();
        $publishedServices = Service::query()->where('status', 'published')->count();
        $professionalsWithPublishedServices = ProfessionalProfile::query()
            ->publiclyVisible()
            ->whereHas('services', fn ($query) => $query->where('status', 'published'))
            ->count();
        $professionalsWithoutPublishedServices = ProfessionalProfile::query()
            ->whereDoesntHave('services', fn ($query) => $query->where('status', 'published'))
            ->count();

        $successfulTransactions = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->whereBetween('processed_at', [$from, $to]);

        $paymentVolume = $successfulTransactions
            ->select('currency', DB::raw('SUM(amount) AS total'))
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => number_format((float) $value, 2, '.', '')])
            ->all();

        $commissions = Commission::query()
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$from, $to])
            ->select('currency', DB::raw('SUM(commission_amount) AS commission_total'), DB::raw('SUM(net_amount) AS net_total'))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->currency => [
                'commission' => number_format((float) $row->commission_total, 2, '.', ''),
                'professional_net' => number_format((float) $row->net_total, 2, '.', ''),
            ]])
            ->all();

        $transactionStatuses = PaymentTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();

        $withdrawalsPending = Withdrawal::query()->where('status', WithdrawalStatus::REQUESTED->value)->count();
        $withdrawalsForReview = Withdrawal::query()->where('status', WithdrawalStatus::REVIEW_REQUIRED->value)->count();
        $refundsPending = PaymentRefund::query()->whereIn('status', [
            PaymentRefundStatus::REQUESTED->value,
            PaymentRefundStatus::PROCESSING->value,
        ])->count();
        $openReports = Report::query()->where('status', 'pending')->count();
        $openTickets = SupportTicket::query()->where('status', 'open')->count();
        $flaggedReviews = Report::query()
            ->where('target_type', Review::class)
            ->where('status', 'pending')
            ->count();

        $requestsByCategory = Category::query()
            ->leftJoin('services', 'services.category_id', '=', 'categories.id')
            ->leftJoin('service_requests', 'service_requests.service_id', '=', 'services.id')
            ->whereBetween('service_requests.created_at', [$from, $to])
            ->select('categories.id', 'categories.name', DB::raw('COUNT(service_requests.id) AS requests_count'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('requests_count')
            ->limit(8)
            ->get();

        $recentOrders = Order::query()
            ->with(['client:id,name', 'professional.user:id,name'])
            ->latest('created_at')->latest('id')->limit(6)->get();

        $recentTransactions = PaymentTransaction::query()
            ->with(['payment:id,order_id,client_id', 'payment.order:id,order_number', 'payment.client:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')->latest('id')->limit(6)->get();

        $orderStatuses = Order::query()
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();

        $requestsInPeriod = ServiceRequest::query()->whereBetween('created_at', [$from, $to])->where('status', '<>', 'draft')->count();
        $quotationsInPeriod = Quotation::query()->whereBetween('created_at', [$from, $to])->count();
        $acceptedQuotationsInPeriod = Quotation::query()->whereBetween('created_at', [$from, $to])->where('status', 'accepted')->count();
        $ordersInPeriod = Order::query()->whereBetween('created_at', [$from, $to])->count();
        $completedOrdersInPeriod = Order::query()->whereBetween('completed_at', [$from, $to])->count();
        $failedNotifications = NotificationDelivery::query()->where('status', 'failed')->count();
        $pendingNotifications = NotificationDelivery::query()->whereIn('status', ['pending', 'queued'])->count();

        $days = min(366, max(1, (int) $from->diffInDays($to) + 1));
        $startOfFirstDay = CarbonImmutable::instance($from)->startOfDay();
        $endOfLastDay = CarbonImmutable::instance($to)->endOfDay();
        $clientRegistrationRows = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'client'))
            ->whereBetween('created_at', [$startOfFirstDay, $endOfLastDay])
            ->selectRaw('DATE(created_at) as registration_date, COUNT(*) as aggregate')
            ->groupBy('registration_date')
            ->pluck('aggregate', 'registration_date');
        $professionalRegistrationRows = ProfessionalProfile::query()
            ->whereBetween('created_at', [$startOfFirstDay, $endOfLastDay])
            ->selectRaw('DATE(created_at) as registration_date, COUNT(*) as aggregate')
            ->groupBy('registration_date')
            ->pluck('aggregate', 'registration_date');
        $chartLabels = [];
        $clientRegistrations = [];
        $professionalRegistrations = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $day = $startOfFirstDay->addDays($offset);
            $dateKey = $day->toDateString();
            $chartLabels[] = $day->format('d/m');
            $clientRegistrations[] = (int) ($clientRegistrationRows[$dateKey] ?? 0);
            $professionalRegistrations[] = (int) ($professionalRegistrationRows[$dateKey] ?? 0);
        }

        $ratio = static fn (int $numerator, int $denominator): float => $denominator > 0
            ? round(($numerator / $denominator) * 100, 1)
            : 0.0;

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'users' => ['total' => $usersTotal, 'new' => $usersNew],
            'professionals' => [
                'total' => $professionalsTotal,
                'new' => $professionalsNew,
                'verification' => [
                    'pending' => $verification[ProfessionalVerificationStatus::PENDING->value] ?? 0,
                    'under_review' => $verification[ProfessionalVerificationStatus::UNDER_REVIEW->value] ?? 0,
                    'verified' => $verification[ProfessionalVerificationStatus::VERIFIED->value] ?? 0,
                    'rejected' => $verification[ProfessionalVerificationStatus::REJECTED->value] ?? 0,
                ],
            ],
            'clients' => ['total' => $clientsTotal, 'new' => $clientsNew],
            'community' => [
                'suspended' => $suspendedUsers,
                'professionals_with_published_services' => $professionalsWithPublishedServices,
                'professionals_without_published_services' => $professionalsWithoutPublishedServices,
                'published_services' => $publishedServices,
            ],
            'services' => $this->statusCounts(Service::class, $from, $to),
            'requests' => $this->statusCounts(ServiceRequest::class, $from, $to),
            'orders' => $this->statusCounts(Order::class, $from, $to),
            'payments' => $this->statusCounts(PaymentTransaction::class, $from, $to),
            'quotations' => $this->statusCounts(Quotation::class, $from, $to),
            'funnel' => [
                'requests' => $requestsInPeriod,
                'quotations' => $quotationsInPeriod,
                'accepted_quotations' => $acceptedQuotationsInPeriod,
                'orders' => $ordersInPeriod,
                'completed_orders' => $completedOrdersInPeriod,
                'request_to_quote_rate' => $ratio($quotationsInPeriod, $requestsInPeriod),
                'quote_acceptance_rate' => $ratio($acceptedQuotationsInPeriod, $quotationsInPeriod),
                'completion_rate' => $ratio($completedOrdersInPeriod, $ordersInPeriod),
            ],
            'charts' => [
                'labels' => $chartLabels,
                'registrations' => ['clients' => $clientRegistrations, 'professionals' => $professionalRegistrations],
                'order_statuses' => $orderStatuses,
            ],
            'financial' => [
                'payment_volume' => $paymentVolume,
                'commissions' => $commissions,
                'transaction_statuses' => $transactionStatuses,
                'withdrawals_pending' => $withdrawalsPending,
                'withdrawals_review' => $withdrawalsForReview,
                'refunds_pending' => $refundsPending,
            ],
            'attention' => [
                'verification' => $verification[ProfessionalVerificationStatus::PENDING->value] ?? 0,
                'withdrawals' => $withdrawalsPending,
                'reports' => $openReports,
                'tickets' => $openTickets,
                'payment_anomalies' => $transactionStatuses['unknown'] ?? 0,
                'reviews' => $flaggedReviews,
            ],
            'catalog' => ['requests_by_category' => $requestsByCategory],
            'recent_orders' => $recentOrders,
            'recent_transactions' => $recentTransactions,
            'health' => [
                'failed_notifications' => $failedNotifications,
                'pending_notifications' => $pendingNotifications,
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function statusCounts(string $model, CarbonInterface $from, CarbonInterface $to, string $column = 'status'): array
    {
        return $model::query()
            ->whereBetween('created_at', [$from, $to])
            ->select($column, DB::raw('COUNT(*) AS aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();
    }
}
