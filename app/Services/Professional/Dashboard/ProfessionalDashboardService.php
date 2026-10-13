<?php

declare(strict_types=1);

namespace App\Services\Professional\Dashboard;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ReviewStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Commission;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfessionalDashboardService
{
    private const PERIODS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '12m' => 365,
    ];

    public function getFor(User $user, string $requestedPeriod = '30d'): array
    {
        $period = array_key_exists($requestedPeriod, self::PERIODS) ? $requestedPeriod : '30d';
        $now = CarbonImmutable::now();
        $start = $period === '12m'
            ? $now->startOfMonth()->subMonths(11)
            : $now->startOfDay()->subDays(self::PERIODS[$period] - 1);
        $end = $now;

        $profile = $user->professionalProfile()
            ->with(['user.profile'])
            ->withCount([
                'services',
                'skills',
                'services as published_services_count' => fn (Builder $query) => $query
                    ->where('status', ServiceStatus::PUBLISHED->value)
                    ->whereNotNull('published_at'),
                'services as draft_services_count' => fn (Builder $query) => $query
                    ->where('status', ServiceStatus::DRAFT->value),
                'services as unpublished_services_count' => fn (Builder $query) => $query
                    ->where('status', ServiceStatus::UNPUBLISHED->value),
                'services as archived_services_count' => fn (Builder $query) => $query
                    ->where('status', ServiceStatus::ARCHIVED->value),
                'favorites as received_favorites_count',
            ])
            ->firstOrFail();

        $professionalId = (int) $profile->getKey();
        $unreadNotifications = $user->notifications()
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->count();

        $requestQuery = ServiceRequest::query()->where('professional_id', $professionalId);
        $orderQuery = Order::query()->where('professional_id', $professionalId);
        $quotationQuery = Quotation::query()->whereHas(
            'serviceRequest',
            fn (Builder $query) => $query->where('professional_id', $professionalId),
        );

        $requestsToHandle = (clone $requestQuery)
            ->where('status', ServiceRequestStatus::REQUESTED->value)
            ->count();

        $quotationsRequiringProfessionalAction = (clone $quotationQuery)
            ->where('status', QuotationStatus::NEGOTIATING->value)
            ->whereHas('currentOffer', fn (Builder $query) => $query
                ->where('actor_type', QuotationOfferActor::CLIENT->value))
            ->count();

        $activeOrderStatuses = [
            OrderStatus::PENDING_PAYMENT->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PROGRESS->value,
        ];

        $activeOrders = (clone $orderQuery)->whereIn('status', $activeOrderStatuses)->count();
        $completedOrders = (clone $orderQuery)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->count();

        $wallets = Wallet::query()
            ->where('professional_id', $professionalId)
            ->orderBy('currency')
            ->get(['currency', 'available_balance', 'pending_balance', 'locked_balance']);

        $pendingWithdrawals = Withdrawal::query()
            ->where('professional_id', $professionalId)
            ->whereIn('status', [
                WithdrawalStatus::REQUESTED->value,
                WithdrawalStatus::PROCESSING->value,
            ])
            ->count();

        $unreadMessages = $this->unreadConversationCount($user);

        $profileData = $this->profile($profile);
        $finance = $this->finance($professionalId, $wallets, $start, $end, $period);
        $charts = $this->charts($professionalId, $requestQuery, $orderQuery, $start, $end, $period);
        $pendingActions = $this->pendingActions(
            $profile,
            $requestsToHandle,
            $quotationsRequiringProfessionalAction,
            $unreadMessages,
            $unreadNotifications,
        );

        return [
            'profile' => $profileData,
            'services' => [
                'total' => (int) $profile->services_count,
                'published' => (int) $profile->published_services_count,
                'draft' => (int) $profile->draft_services_count,
                'unpublished' => (int) $profile->unpublished_services_count,
                'archived' => (int) $profile->archived_services_count,
            ],
            'engagement' => [
                'received_favorites' => (int) $profile->received_favorites_count,
                // These cached aggregates are maintained by ReviewService from published reviews.
                'rating_average' => $profile->rating_average === null ? null : (float) $profile->rating_average,
                'rating_count' => (int) $profile->rating_count,
            ],
            'notifications' => [
                'unread' => $unreadNotifications,
                'messages_unread' => $unreadMessages,
            ],
            'operations' => [
                'requests_to_handle' => $requestsToHandle,
                'quotations_requiring_action' => $quotationsRequiringProfessionalAction,
                'active_orders' => $activeOrders,
                'completed_orders' => $completedOrders,
                'pending_withdrawals' => $pendingWithdrawals,
            ],
            'finance' => $finance,
            'charts' => $charts,
            'pending_actions' => $pendingActions,
            'recent_activity' => $this->recentActivity($professionalId),
            'upcoming_orders' => $this->upcomingOrders($professionalId, $now),
            'period' => $period,
            'period_label' => match ($period) {
                '7d' => '7 derniers jours',
                '90d' => '90 derniers jours',
                '12m' => '12 derniers mois',
                default => '30 derniers jours',
            },
            'generated_at' => $now,
        ];
    }

    private function profile(ProfessionalProfile $profile): array
    {
        $userProfile = $profile->user->profile;
        // Keep this score aligned with the existing profile page's seven completion criteria.
        $completionItems = [
            'personal' => $userProfile !== null,
            'profession' => filled($profile->professional_title),
            'presentation' => filled($profile->description),
            'service_area' => filled($profile->city) && filled($profile->province),
            'skills' => (int) $profile->skills_count > 0,
            'services' => (int) $profile->services_count > 0,
            'verification' => $profile->verification_status?->value === 'verified',
        ];
        $completionLabels = [
            'personal' => 'Informations personnelles',
            'profession' => 'Spécialité professionnelle',
            'presentation' => 'Présentation professionnelle',
            'service_area' => 'Zone d’intervention (ville et province)',
            'skills' => 'Compétences professionnelles',
            'services' => 'Au moins un service',
            'verification' => 'Vérification professionnelle',
        ];
        $missingFields = collect($completionItems)
            ->filter(fn (bool $complete): bool => ! $complete)
            ->keys()
            ->map(fn (string $key): string => $completionLabels[$key])
            ->values()
            ->all();

        return [
            'id' => $profile->getKey(),
            'professional_title' => $profile->professional_title,
            'status' => $profile->status,
            'verification_status' => $profile->verification_status?->value,
            'availability_status' => $profile->availability_status?->value,
            // Preserve the established API dashboard response contract.
            'rating_average' => $profile->rating_average,
            'rating_count' => (int) $profile->rating_count,
            'profile_completion' => (int) round((count($completionItems) - count($missingFields)) / count($completionItems) * 100),
            'missing_fields' => $missingFields,
            'user' => [
                'id' => $profile->user->getKey(),
                'name' => $profile->user->name,
                'account_status' => $profile->user->account_status?->value,
                'profile' => $userProfile?->only([
                    'first_name',
                    'last_name',
                    'phone',
                    'bio',
                    'locale',
                    'timezone',
                ]),
            ],
        ];
    }

    private function finance(int $professionalId, Collection $wallets, CarbonImmutable $start, CarbonImmutable $end, string $period): array
    {
        $revenues = Commission::query()
            ->where('professional_id', $professionalId)
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$start, $end])
            ->selectRaw('currency, SUM(gross_amount) as gross_amount, SUM(commission_amount) as commission_amount, SUM(net_amount) as net_amount, COUNT(*) as entries_count')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row): array => [
                'currency' => $row->currency,
                'gross_amount' => (string) $row->gross_amount,
                'commission_amount' => (string) $row->commission_amount,
                'net_amount' => (string) $row->net_amount,
                'entries_count' => (int) $row->entries_count,
            ])
            ->values()
            ->all();

        return [
            'revenues' => $revenues,
            'wallets' => $wallets->map(fn ($wallet): array => [
                'currency' => $wallet->currency,
                'available_balance' => (string) $wallet->available_balance,
                'pending_balance' => (string) $wallet->pending_balance,
                'locked_balance' => (string) $wallet->locked_balance,
            ])->values()->all(),
            'wallet_available_known' => $wallets->isNotEmpty(),
            'pending_withdrawals' => Withdrawal::query()
                ->where('professional_id', $professionalId)
                ->whereIn('status', [WithdrawalStatus::REQUESTED->value, WithdrawalStatus::PROCESSING->value])
                ->count(),
            'period' => $period,
        ];
    }

    private function charts(int $professionalId, Builder $requestQuery, Builder $orderQuery, CarbonImmutable $start, CarbonImmutable $end, string $period): array
    {
        $monthly = $period === '12m';
        $labels = $monthly
            ? collect(range(0, 11))->map(fn (int $offset) => $start->addMonths($offset)->format('Y-m'))->all()
            : collect(range(0, self::PERIODS[$period] - 1))->map(fn (int $offset) => $start->addDays($offset)->format('Y-m-d'))->all();

        $requestsByDate = $this->countByDate((clone $requestQuery)->whereNotNull('requested_at')->whereBetween('requested_at', [$start, $end]), 'requested_at', $monthly);
        $ordersByDate = $this->countByDate((clone $orderQuery)->whereBetween('created_at', [$start, $end]), 'created_at', $monthly);
        $completedByDate = $this->countByDate(
            (clone $orderQuery)->where('status', OrderStatus::COMPLETED->value)->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end]),
            'completed_at',
            $monthly,
        );

        $revenueRows = Commission::query()
            ->where('professional_id', $professionalId)
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$start, $end])
            ->selectRaw('DATE(posted_at) as bucket, currency, SUM(net_amount) as amount')
            ->groupBy('bucket', 'currency')
            ->orderBy('bucket')
            ->get();

        $revenueCurrencies = $revenueRows->pluck('currency')->unique()->sort()->values();
        $revenueSeries = $revenueCurrencies->map(function (string $currency) use ($revenueRows, $labels, $monthly): array {
            $values = array_fill(0, count($labels), 0.0);
            foreach ($revenueRows->where('currency', $currency) as $row) {
                $key = $monthly ? CarbonImmutable::parse($row->bucket)->format('Y-m') : (string) $row->bucket;
                $index = array_search($key, $labels, true);
                if ($index !== false) {
                    $values[$index] += (float) $row->amount;
                }
            }

            return ['label' => $currency, 'data' => $values];
        })->values()->all();

        $orderDistribution = collect(OrderStatus::cases())->map(function (OrderStatus $status) use ($orderQuery): array {
            return [
                'status' => $status->value,
                'label' => match ($status) {
                    OrderStatus::PENDING_PAYMENT => 'En attente de paiement',
                    OrderStatus::CONFIRMED => 'Confirmées',
                    OrderStatus::IN_PROGRESS => 'En cours',
                    OrderStatus::COMPLETED => 'Terminées',
                    OrderStatus::CANCELLED => 'Annulées',
                    OrderStatus::DISPUTED => 'En litige',
                },
                'count' => (clone $orderQuery)->where('status', $status->value)->count(),
            ];
        })->values()->all();

        $topServices = ServiceRequest::query()
            ->where('service_requests.professional_id', $professionalId)
            ->whereNotNull('service_requests.requested_at')
            ->whereBetween('service_requests.requested_at', [$start, $end])
            ->join('services', 'services.id', '=', 'service_requests.service_id')
            ->selectRaw('service_requests.service_id, services.title as service_title, COUNT(*) as requests_count')
            ->groupBy('service_requests.service_id', 'services.title')
            ->orderByDesc('requests_count')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'service_id' => (int) $row->service_id,
                'label' => (string) $row->service_title,
                'count' => (int) $row->requests_count,
            ])
            ->values()
            ->all();

        return [
            'activity' => [
                'labels' => $labels,
                'requests' => array_map(fn ($label) => $requestsByDate[$label] ?? 0, $labels),
                'orders' => array_map(fn ($label) => $ordersByDate[$label] ?? 0, $labels),
                'completed' => array_map(fn ($label) => $completedByDate[$label] ?? 0, $labels),
                'monthly' => $monthly,
            ],
            'revenue' => [
                'labels' => $labels,
                'series' => $revenueSeries,
                'monthly' => $monthly,
            ],
            'order_distribution' => $orderDistribution,
            'top_services' => $topServices,
        ];
    }

    private function countByDate(Builder $query, string $dateColumn, bool $monthly): array
    {
        $rows = $query->selectRaw('DATE('.$dateColumn.') as bucket, COUNT(*) as aggregate')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $key = $monthly ? CarbonImmutable::parse($row->bucket)->format('Y-m') : (string) $row->bucket;
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->aggregate;
        }

        return $counts;
    }

    private function unreadConversationCount(User $user): int
    {
        return (int) DB::table('conversation_participants as cp')
            ->join('messages as m', 'm.conversation_id', '=', 'cp.conversation_id')
            ->where('cp.user_id', $user->getKey())
            ->whereNull('cp.left_at')
            ->whereNull('cp.archived_at')
            ->where('m.sender_id', '!=', $user->getKey())
            ->whereRaw('m.id > COALESCE(cp.last_read_message_id, 0)')
            ->distinct()
            ->count('cp.conversation_id');
    }

    private function pendingActions(
        ProfessionalProfile $profile,
        int $requestsToHandle,
        int $quotationsRequiringAction,
        int $unreadMessages,
        int $unreadNotifications,
    ): Collection {
        $actions = collect();

        if ($profile->status === ProfessionalProfile::STATUS_SUSPENDED) {
            $actions->push([
                'type' => 'profile',
                'priority' => 'high',
                'message' => 'Votre profil professionnel est suspendu. Consultez son état et les instructions disponibles.',
                'url' => route('professional.profile'),
            ]);
        }

        if ($profile->services_count === 0) {
            $actions->push([
                'type' => 'services',
                'priority' => 'normal',
                'message' => 'Créez votre premier service professionnel.',
                'url' => route('professional.services.index'),
            ]);
        } elseif ($profile->published_services_count === 0) {
            $actions->push([
                'type' => 'services',
                'priority' => 'normal',
                'message' => 'Publiez au moins un service pour apparaître dans le catalogue.',
                'url' => route('professional.services.index'),
            ]);
        }

        $profileIsComplete = $profile->user->profile !== null
            && filled($profile->professional_title)
            && filled($profile->description)
            && filled($profile->city)
            && filled($profile->province)
            && (int) $profile->skills_count > 0
            && (int) $profile->services_count > 0
            && $profile->verification_status?->value === 'verified';

        if (! $profileIsComplete && $profile->status !== ProfessionalProfile::STATUS_SUSPENDED) {
            $actions->push([
                'type' => 'profile',
                'priority' => 'normal',
                'message' => 'Votre profil est complété à '.$this->profile($profile)['profile_completion'].' %. Consultez les éléments qui restent à renseigner.',
                'url' => route('professional.profile.edit'),
            ]);
        }

        if ($profile->verification_status?->value === 'rejected') {
            $actions->push([
                'type' => 'documents',
                'priority' => 'high',
                'message' => 'La vérification a été rejetée. Consultez vos documents et les informations demandées.',
                'url' => route('professional.documents.index'),
            ]);
        }

        if ($requestsToHandle > 0) {
            $actions->push([
                'type' => 'requests',
                'priority' => 'high',
                'count' => $requestsToHandle,
                'message' => $requestsToHandle.' demande(s) attend(ent) votre examen.',
                'url' => route('professional.requests.index', ['status' => ServiceRequestStatus::REQUESTED->value]),
            ]);
        }

        if ($quotationsRequiringAction > 0) {
            $actions->push([
                'type' => 'quotes',
                'priority' => 'high',
                'count' => $quotationsRequiringAction,
                'message' => $quotationsRequiringAction.' devis en négociation attend(ent) votre réponse.',
                'url' => route('professional.quotes.index'),
            ]);
        }

        if ($unreadMessages > 0) {
            $actions->push([
                'type' => 'messages',
                'priority' => 'normal',
                'count' => $unreadMessages,
                'message' => $unreadMessages.' conversation(s) contient/contiennent des messages non lus.',
                'url' => route('professional.messages'),
            ]);
        }

        if ($unreadNotifications > 0) {
            $actions->push([
                'type' => 'notifications',
                'priority' => 'normal',
                'count' => $unreadNotifications,
                'message' => $unreadNotifications.' notification(s) non lue(s).',
                'url' => route('professional.notifications'),
            ]);
        }

        return $actions->values();
    }

    private function recentActivity(int $professionalId): Collection
    {
        $events = collect();

        ServiceRequest::query()
            ->where('professional_id', $professionalId)
            ->whereNotNull('requested_at')
            ->with('service:id,title')
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->each(fn (ServiceRequest $request) => $events->push([
                'type' => 'request',
                'title' => 'Demande de service reçue',
                'description' => $request->service?->title ?? $request->title,
                'at' => $request->requested_at,
                'url' => route('professional.requests.show', $request),
            ]));

        Quotation::query()
            ->whereHas('serviceRequest', fn (Builder $query) => $query->where('professional_id', $professionalId))
            ->with('serviceRequest:id,title')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(fn (Quotation $quotation) => $events->push([
                'type' => 'quote',
                'title' => 'Devis mis à jour',
                'description' => $quotation->serviceRequest?->title ?? ('Devis #'.$quotation->getKey()),
                'at' => $quotation->updated_at,
                'url' => route('professional.quotes.show', $quotation),
            ]));

        Order::query()
            ->where('professional_id', $professionalId)
            ->with('serviceRequest:id,title')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(fn (Order $order) => $events->push([
                'type' => 'order',
                'title' => 'Commande mise à jour',
                'description' => $order->order_number ?? ('Commande #'.$order->getKey()),
                'at' => $order->updated_at,
                'url' => route('professional.orders.show', $order),
            ]));

        Review::query()
            ->where('professional_id', $professionalId)
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->each(fn (Review $review) => $events->push([
                'type' => 'review',
                'title' => 'Nouvel avis publié',
                'description' => $review->rating.'/5',
                'at' => $review->published_at ?? $review->created_at,
                'url' => route('professional.reviews'),
            ]));

        Commission::query()
            ->where('professional_id', $professionalId)
            ->where('status', CommissionStatus::POSTED->value)
            ->latest('posted_at')
            ->limit(3)
            ->get()
            ->each(fn (Commission $commission) => $events->push([
                'type' => 'revenue',
                'title' => 'Revenu comptabilisé',
                'description' => number_format((float) $commission->net_amount, 2, ',', ' ').' '.$commission->currency,
                'at' => $commission->posted_at,
                'url' => route('professional.revenues.index', [
                    'from' => $commission->posted_at?->toDateString(),
                    'to' => $commission->posted_at?->toDateString(),
                    'currency' => $commission->currency,
                ]),
            ]));

        Withdrawal::query()
            ->where('professional_id', $professionalId)
            ->latest('requested_at')
            ->limit(3)
            ->get()
            ->each(fn (Withdrawal $withdrawal) => $events->push([
                'type' => 'withdrawal',
                'title' => 'Demande de retrait mise à jour',
                'description' => number_format((float) $withdrawal->amount, 2, ',', ' ').' '.$withdrawal->currency.' · '.$withdrawal->status->value,
                'at' => $withdrawal->updated_at ?? $withdrawal->requested_at,
                'url' => route('professional.withdrawals.show', $withdrawal),
            ]));

        return $events->sortByDesc(fn (array $event) => $event['at']?->getTimestamp() ?? 0)->take(6)->values();
    }

    private function upcomingOrders(int $professionalId, CarbonImmutable $now): Collection
    {
        return Order::query()
            ->where('professional_id', $professionalId)
            ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::IN_PROGRESS->value])
            ->whereHas('serviceRequest', fn (Builder $query) => $query
                ->whereNotNull('desired_at')
                ->where('desired_at', '>=', $now))
            ->with(['serviceRequest:id,title,desired_at'])
            ->whereHas('serviceRequest')
            ->get()
            ->sortBy(fn (Order $order) => $order->serviceRequest?->desired_at?->getTimestamp() ?? PHP_INT_MAX)
            ->take(5)
            ->values()
            ->map(fn (Order $order): array => [
                'id' => $order->getKey(),
                'reference' => $order->order_number ?? ('Commande #'.$order->getKey()),
                'title' => $order->serviceRequest?->title ?? 'Prestation professionnelle',
                'desired_at' => $order->serviceRequest?->desired_at,
                'status' => $order->status->value,
                'url' => route('professional.orders.show', $order),
            ]);
    }
}
