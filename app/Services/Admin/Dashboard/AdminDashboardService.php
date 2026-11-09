<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Enums\SupportTicketStatus;
use App\Models\Address;
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

        $periodDays = min(366, max(1, (int) $from->diffInDays($to) + 1));
        $previousFrom = CarbonImmutable::instance($from)->subDays($periodDays);
        $previousTo = CarbonImmutable::instance($from)->subSecond();
        $previousUsersNew = User::query()->whereBetween('created_at', [$previousFrom, $previousTo])->count();

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

        $previousClientsNew = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'client'))
            ->whereBetween('users.created_at', [$previousFrom, $previousTo])
            ->count();
        $previousProfessionalsNew = ProfessionalProfile::query()
            ->whereBetween('created_at', [$previousFrom, $previousTo])
            ->count();

        $suspendedUsers = User::query()->where('account_status', 'suspended')->count();
        $activeOrders = Order::query()->whereIn('status', OrderStatus::operationallyActiveValues())->count();
        $completedOrders = Order::query()->where('status', OrderStatus::COMPLETED->value)->count();
        $cancelledOrders = Order::query()->where('status', OrderStatus::CANCELLED->value)->count();
        $confirmedTransactionsCount = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->whereBetween('processed_at', [$from, $to])
            ->count();
        $activeCategories = Category::query()->where('status', 'active')->count();
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
        $openTickets = SupportTicket::query()->whereIn('status', [
            SupportTicketStatus::OPEN->value,
            SupportTicketStatus::IN_PROGRESS->value,
            SupportTicketStatus::WAITING->value,
        ])->count();
        $flaggedReviews = Report::query()
            ->where('target_type', Review::class)
            ->where('status', 'pending')
            ->count();

        $topServices = Service::query()
            ->with('category:id,name')
            ->withCount(['serviceRequests' => fn ($query) => $query->where('status', '<>', 'draft')->whereBetween('requested_at', [$from, $to])])
            ->withCount(['serviceRequests as orders_count' => fn ($query) => $query
                ->where('status', '<>', 'draft')
                ->whereBetween('requested_at', [$from, $to])
                ->whereHas('order')])
            ->orderByDesc('service_requests_count')
            ->limit(6)
            ->get(['id', 'title', 'category_id']);

        $clientsByZone = Address::query()
            ->where('is_default', true)
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->whereHas('user.roles', fn ($roles) => $roles->where('name', 'client'))
            ->select('province', 'city', DB::raw('COUNT(DISTINCT user_id) AS clients_count'))
            ->groupBy('province', 'city')
            ->orderByDesc('clients_count')
            ->limit(8)
            ->get();

        $professionalsByZone = ProfessionalProfile::query()
            ->publiclyVisible()
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->select('province', 'city', DB::raw('COUNT(*) AS professionals_count'))
            ->groupBy('province', 'city')
            ->orderByDesc('professionals_count')
            ->limit(8)
            ->get();

        $requestsByCategory = Category::query()
            ->leftJoin('services', 'services.category_id', '=', 'categories.id')
            ->leftJoin('service_requests', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.status', '<>', 'draft')
            ->whereBetween('service_requests.requested_at', [$from, $to])
            ->select('categories.id', 'categories.name', DB::raw('COUNT(service_requests.id) AS requests_count'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('requests_count')
            ->limit(8)
            ->get();

        $recentOrders = Order::query()
            ->with(['client:id,name', 'professional.user:id,name'])
            ->latest('created_at')->latest('id')->limit(6)->get();

        $recentTransactions = PaymentTransaction::query()
            ->with(['payment:id,order_id,client_id,provider', 'payment.order:id,order_number', 'payment.client:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')->latest('id')->limit(6)->get();

        $orderStatuses = Order::query()
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();

        $requestsInPeriod = ServiceRequest::query()->whereBetween('requested_at', [$from, $to])->where('status', '<>', 'draft')->count();
        $quotationsInPeriod = ServiceRequest::query()
            ->whereBetween('requested_at', [$from, $to])
            ->where('status', 'quoted')
            ->count();
        $acceptedQuotationsInPeriod = ServiceRequest::query()
            ->whereBetween('requested_at', [$from, $to])
            ->where('status', 'accepted')
            ->count();
        $ordersInPeriod = Order::query()->whereBetween('created_at', [$from, $to])->count();
        $completedOrdersInPeriod = Order::query()->whereBetween('completed_at', [$from, $to])->count();

        $previousRequestsInPeriod = ServiceRequest::query()
            ->whereBetween('requested_at', [$previousFrom, $previousTo])
            ->where('status', '<>', 'draft')
            ->count();
        $previousOrdersInPeriod = Order::query()->whereBetween('created_at', [$previousFrom, $previousTo])->count();
        $previousConfirmedTransactionsCount = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->whereBetween('processed_at', [$previousFrom, $previousTo])
            ->count();
        $completedOrdersFromPeriodCohort = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::COMPLETED->value)
            ->count();
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
        $chartDates = [];
        $clientRegistrations = [];
        $professionalRegistrations = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $day = $startOfFirstDay->addDays($offset);
            $dateKey = $day->toDateString();
            $chartLabels[] = $day->format('d/m');
            $chartDates[] = $dateKey;
            $clientRegistrations[] = (int) ($clientRegistrationRows[$dateKey] ?? 0);
            $professionalRegistrations[] = (int) ($professionalRegistrationRows[$dateKey] ?? 0);
        }


        $dailyPayments = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('DATE(processed_at) AS activity_date, currency, SUM(amount) AS total')
            ->groupBy('activity_date', 'currency')
            ->get();
        $dailyCommissions = Commission::query()
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$from, $to])
            ->selectRaw('DATE(posted_at) AS activity_date, currency, SUM(commission_amount) AS total')
            ->groupBy('activity_date', 'currency')
            ->get();
        $financialTrend = [];
        $financialCurrencies = $dailyPayments->pluck('currency')
            ->merge($dailyCommissions->pluck('currency'))
            ->filter()
            ->unique()
            ->values();

        foreach ($financialCurrencies as $currency) {
            $paymentsByDate = $dailyPayments->where('currency', $currency)->keyBy('activity_date');
            $commissionsByDate = $dailyCommissions->where('currency', $currency)->keyBy('activity_date');
            $paymentValues = [];
            $commissionValues = [];

            foreach ($chartDates as $date) {
                $paymentValues[] = round((float) ($paymentsByDate->get($date)?->total ?? 0), 2);
                $commissionValues[] = round((float) ($commissionsByDate->get($date)?->total ?? 0), 2);
            }

            $financialTrend[(string) $currency] = [
                'labels' => $chartLabels,
                'dates' => $chartDates,
                'payments' => $paymentValues,
                'commissions' => $commissionValues,
            ];
        }

        $ratio = static fn (int $numerator, int $denominator): float => $denominator > 0
            ? round(($numerator / $denominator) * 100, 1)
            : 0.0;

        $variation = static fn (int|float $current, int|float $previous): ?float => $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : ($current === 0 ? 0.0 : null);

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'comparisons' => [
                'users_new' => ['previous' => $previousUsersNew, 'change_percent' => $variation($usersNew, $previousUsersNew)],
                'clients_new' => ['previous' => $previousClientsNew, 'change_percent' => $variation($clientsNew, $previousClientsNew)],
                'professionals_new' => ['previous' => $previousProfessionalsNew, 'change_percent' => $variation($professionalsNew, $previousProfessionalsNew)],
                'requests' => ['previous' => $previousRequestsInPeriod, 'change_percent' => $variation($requestsInPeriod, $previousRequestsInPeriod)],
                'orders' => ['previous' => $previousOrdersInPeriod, 'change_percent' => $variation($ordersInPeriod, $previousOrdersInPeriod)],
                'confirmed_transactions' => ['previous' => $previousConfirmedTransactionsCount, 'change_percent' => $variation($confirmedTransactionsCount, $previousConfirmedTransactionsCount)],
            ],
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
                'active_categories' => $activeCategories,
                'active_orders' => $activeOrders,
                'completed_orders_current' => $completedOrders,
                'cancelled_orders_current' => $cancelledOrders,
                'confirmed_transactions_count' => $confirmedTransactionsCount,
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
                'completed_orders_from_cohort' => $completedOrdersFromPeriodCohort,
                'request_to_quote_rate' => $ratio($quotationsInPeriod + $acceptedQuotationsInPeriod, $requestsInPeriod),
                'quote_acceptance_rate' => $ratio($acceptedQuotationsInPeriod, $quotationsInPeriod + $acceptedQuotationsInPeriod),
                'completion_rate' => $ratio($completedOrdersFromPeriodCohort, $ordersInPeriod),
            ],
            'charts' => [
                'labels' => $chartLabels,
                'dates' => $chartDates,
                'registrations' => ['clients' => $clientRegistrations, 'professionals' => $professionalRegistrations],
                'order_statuses' => $orderStatuses,
            ],
            'financial' => [
                'payment_volume' => $paymentVolume,
                'confirmed_transactions_count' => $confirmedTransactionsCount,
                'commissions' => $commissions,
                'transaction_statuses' => $transactionStatuses,
                'trend' => $financialTrend,
                'withdrawals_pending' => $withdrawalsPending,
                'withdrawals_review' => $withdrawalsForReview,
                'refunds_pending' => $refundsPending,
            ],
            'attention' => [
                'verification' => $verification[ProfessionalVerificationStatus::PENDING->value] ?? 0,
                'withdrawals' => $withdrawalsPending + $withdrawalsForReview,
                'reports' => $openReports,
                'tickets' => $openTickets,
                'payment_anomalies' => $transactionStatuses['unknown'] ?? 0,
                'reviews' => $flaggedReviews,
            ],
            'catalog' => [
                'requests_by_category' => $requestsByCategory,
                'active_categories' => $activeCategories,
                'top_services' => $topServices,
            ],
            'geography' => ['professionals_by_zone' => $professionalsByZone, 'clients_by_zone' => $clientsByZone],
            'recent_orders' => $recentOrders,
            'recent_transactions' => $recentTransactions,
            'recent_activity' => $this->recentActivity($from, $to),
            'health' => [
                'failed_notifications' => $failedNotifications,
                'pending_notifications' => $pendingNotifications,
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }


    private function recentActivity(CarbonInterface $from, CarbonInterface $to): array
    {
        $items = collect();

        foreach (User::query()->whereBetween('created_at', [$from, $to])->latest('created_at')->limit(4)->get(['id', 'name', 'created_at']) as $user) {
            $items->push([
                'type' => 'Utilisateur',
                'title' => $user->name,
                'description' => 'Nouveau compte inscrit',
                'occurred_at' => $user->created_at,
                'url' => route('admin.users.show', $user),
                'permission' => 'admin.users.view',
            ]);
        }

        foreach (ProfessionalProfile::query()->with('user:id,name')->whereBetween('created_at', [$from, $to])->latest('created_at')->limit(4)->get() as $professional) {
            $items->push([
                'type' => 'Professionnel',
                'title' => $professional->user?->name ?? 'Professionnel',
                'description' => 'Nouveau profil professionnel créé',
                'occurred_at' => $professional->created_at,
                'url' => route('admin.professionals.show', $professional),
                'permission' => 'admin.professionals.view',
            ]);
        }

        foreach (Service::query()->whereBetween('created_at', [$from, $to])->latest('created_at')->limit(4)->get(['id', 'title', 'created_at']) as $service) {
            $items->push([
                'type' => 'Catalogue',
                'title' => $service->title,
                'description' => 'Nouveau service enregistré',
                'occurred_at' => $service->created_at,
                'url' => route('admin.services.show', $service),
                'permission' => 'admin.services.view',
            ]);
        }

        foreach (ServiceRequest::query()->whereBetween('requested_at', [$from, $to])->where('status', '<>', 'draft')->latest('requested_at')->limit(4)->get(['id', 'title', 'requested_at']) as $request) {
            $items->push([
                'type' => 'Demande',
                'title' => $request->title,
                'description' => 'Nouvelle demande de service publiée',
                'occurred_at' => $request->requested_at,
                'url' => route('admin.service-requests.show', $request),
                'permission' => 'requests.view',
            ]);
        }

        foreach (Order::query()->whereBetween('created_at', [$from, $to])->latest('created_at')->limit(4)->get(['id', 'order_number', 'created_at']) as $order) {
            $items->push([
                'type' => 'Commande',
                'title' => $order->order_number ?: 'Commande #'.$order->getKey(),
                'description' => 'Nouvelle commande créée',
                'occurred_at' => $order->created_at,
                'url' => route('admin.orders.show', $order),
                'permission' => 'orders.view',
            ]);
        }

        foreach (PaymentTransaction::query()->with('payment:id')->whereBetween('created_at', [$from, $to])->latest('created_at')->limit(4)->get(['id', 'payment_id', 'status', 'created_at']) as $transaction) {
            if ($transaction->payment !== null) {
                $items->push([
                    'type' => 'Paiement',
                    'title' => 'Transaction #'.$transaction->getKey(),
                    'description' => 'État : '.$transaction->status->value,
                    'occurred_at' => $transaction->created_at,
                    'url' => route('admin.payments.show', $transaction->payment),
                    'permission' => 'payments.view',
                ]);
            }
        }

        foreach (Report::query()->whereBetween('created_at', [$from, $to])->latest('created_at')->limit(4)->get(['id', 'reason_code', 'created_at']) as $report) {
            $items->push([
                'type' => 'Modération',
                'title' => 'Signalement #'.$report->getKey(),
                'description' => 'Motif : '.str_replace('_', ' ', (string) $report->reason_code),
                'occurred_at' => $report->created_at,
                'url' => route('admin.reports.show', $report),
                'permission' => 'reports.manage',
            ]);
        }

        return $items->sortByDesc('occurred_at')->take(12)->values()->all();
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
