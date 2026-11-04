<?php

declare(strict_types=1);

namespace App\Services\Client\Dashboard;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Address;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getFor(User $user): array
    {
        $user->loadMissing('profile');

        $addressesCount = $user->addresses()->count();
        /** @var Address|null $defaultAddress */
        $defaultAddress = $user->addresses()
            ->where('is_default', true)
            ->first([
                'id',
                'label',
                'recipient_name',
                'contact_phone',
                'province',
                'city',
                'commune',
                'neighborhood',
                'address_line_1',
            ]);

        $clientId = (int) $user->getKey();
        $favoritesCount = $user->favorites()->count();
        $unreadNotifications = $user->unreadNotifications()->count();

        $activeRequests = ServiceRequest::query()
            ->where('client_id', $clientId)
            ->whereIn('status', [
                ServiceRequestStatus::REQUESTED->value,
                ServiceRequestStatus::QUOTED->value,
            ]);

        $pendingQuotes = Quotation::query()
            ->whereIn('status', [QuotationStatus::SENT->value, QuotationStatus::NEGOTIATING->value])
            ->whereHas('serviceRequest', fn (Builder $query) => $query->where('client_id', $clientId))
            ->whereHas('currentOffer', fn (Builder $offer) => $offer
                ->where('actor_type', QuotationOfferActor::PROFESSIONAL->value)
                ->where('valid_until', '>', now()));

        $activeOrders = Order::query()
            ->where('client_id', $clientId)
            ->whereIn('status', OrderStatus::operationallyActiveValues());

        $paymentRequiredOrders = Order::query()
            ->where('client_id', $clientId)
            ->where('status', OrderStatus::PENDING_PAYMENT->value);

        $deliveryReviewOrders = Order::query()
            ->where('client_id', $clientId)
            ->where('status', OrderStatus::DELIVERY_SUBMITTED->value);

        $unreadConversations = $this->unreadConversationsQuery($clientId);

        $activeRequestsCount = (clone $activeRequests)->count();
        $pendingQuotesCount = (clone $pendingQuotes)->count();
        $activeOrdersCount = (clone $activeOrders)->count();
        $paymentRequiredCount = (clone $paymentRequiredOrders)->count();
        $unreadConversationsCount = (clone $unreadConversations)->count();

        $pendingActions = $this->pendingActions(
            $pendingQuotes,
            $paymentRequiredOrders,
            $deliveryReviewOrders,
            $unreadConversations,
        );

        $profilePhotoPath = $user->profile?->avatar_path;

        return [
            'profile' => [
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                ],
                'first_name' => $user->profile?->first_name,
                'details' => $user->profile?->only(['first_name', 'last_name']),
                'photo_url' => is_string($profilePhotoPath)
                    && $profilePhotoPath !== ''
                    && Storage::disk('local')->exists($profilePhotoPath)
                    ? route('client.profile.photo')
                    : null,
            ],
            'addresses' => [
                'total' => $addressesCount,
                'default' => $defaultAddress?->toArray(),
            ],
            'favorites' => [
                'count' => $favoritesCount,
                'recent' => $this->recentFavorites($clientId),
            ],
            'notifications' => [
                'unread' => $unreadNotifications,
            ],
            'stats' => [
                'requests' => $activeRequestsCount,
                'quotes' => $pendingQuotesCount,
                'orders' => $activeOrdersCount,
                'payments' => $paymentRequiredCount,
                'messages' => $unreadConversationsCount,
            ],
            'chart' => $this->activityChart($clientId),
            'recent_activity' => $this->recentActivity($clientId),
            'pending_actions' => $pendingActions,
            'professionals' => $this->recommendedProfessionals($defaultAddress),
            'services' => $this->recentServices(),
        ];
    }

    /**
     * @return list<array{type: string, title: string, description: string, url: string}>
     */
    private function pendingActions(
        Builder $pendingQuotes,
        Builder $paymentRequiredOrders,
        Builder $deliveryReviewOrders,
        Builder $unreadConversations,
    ): array {
        $quoteActions = (clone $pendingQuotes)
            ->with(['serviceRequest.service', 'serviceRequest.professional.user'])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Quotation $quote): array => [
                'type' => 'quote',
                'title' => $quote->serviceRequest?->service?->title
                    ?: $quote->serviceRequest?->title
                    ?: 'Un devis attend votre réponse',
                'description' => $quote->serviceRequest?->professional?->business_name
                    ?: $quote->serviceRequest?->professional?->user?->name
                    ?: 'Consultez la proposition du professionnel.',
                'url' => route('client.quotes.show', $quote),
            ]);

        $paymentActions = (clone $paymentRequiredOrders)
            ->latest('created_at')
            ->limit(3)
            ->get(['id', 'order_number', 'total', 'currency'])
            ->map(fn (Order $order): array => [
                'type' => 'payment',
                'title' => $order->order_number ?: 'Commande #'.$order->getKey(),
                'description' => 'Paiement à effectuer · '.number_format((float) $order->total, 2, ',', ' ').' '.$order->currency,
                'url' => route('client.orders.payment', $order),
            ]);

        $deliveryActions = (clone $deliveryReviewOrders)
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'order_number'])
            ->map(fn (Order $order): array => [
                'type' => 'delivery',
                'title' => $order->order_number ?: 'Commande #'.$order->getKey(),
                'description' => 'Une livraison attend votre vérification.',
                'url' => route('client.orders.show', $order),
            ]);

        $messageActions = (clone $unreadConversations)
            ->with(['professional.user', 'lastMessage'])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Conversation $conversation): array => [
                'type' => 'message',
                'title' => $conversation->professional?->business_name
                    ?: $conversation->professional?->user?->name
                    ?: 'Nouveau message',
                'description' => Str::limit((string) $conversation->lastMessage?->body, 90),
                'url' => route('client.messages.show', $conversation),
            ]);

        return $quoteActions
            ->concat($paymentActions)
            ->concat($deliveryActions)
            ->concat($messageActions)
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityChart(int $clientId): array
    {
        $start = CarbonImmutable::now(config('app.timezone'))->startOfMonth()->subMonths(5);
        $end = $start->addMonths(6);
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $months = collect(range(0, 5))->mapWithKeys(function (int $offset) use ($start): array {
            $date = $start->addMonths($offset);

            return [$date->format('Y-m') => [
                'label' => $date->locale('fr')->translatedFormat('F Y'),
                'requests' => 0,
                'orders' => 0,
            ]];
        });

        $requestCounts = ServiceRequest::query()
            ->where('client_id', $clientId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw($monthExpression.' as activity_month, COUNT(*) as activity_total')
            ->groupBy('activity_month')
            ->pluck('activity_total', 'activity_month');

        $orderCounts = Order::query()
            ->where('client_id', $clientId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw($monthExpression.' as activity_month, COUNT(*) as activity_total')
            ->groupBy('activity_month')
            ->pluck('activity_total', 'activity_month');

        foreach ($months as $key => $month) {
            $month['requests'] = (int) ($requestCounts[$key] ?? 0);
            $month['orders'] = (int) ($orderCounts[$key] ?? 0);
            $months->put($key, $month);
        }

        $maximum = max(1, (int) $months->max(fn (array $month): int => max($month['requests'], $month['orders'])));

        return $months->map(function (array $month) use ($maximum): array {
            $month['requests_width'] = (int) round($month['requests'] / $maximum * 100);
            $month['orders_width'] = (int) round($month['orders'] / $maximum * 100);

            return $month;
        })->values()->all();
    }

    /**
     * @return list<array{type: string, label: string, title: string, status: string, url: string, occurred_at: ?string, detail: ?string}>
     */
    private function recentActivity(int $clientId): array
    {
        $requests = ServiceRequest::query()
            ->where('client_id', $clientId)
            ->latest('created_at')
            ->limit(3)
            ->get(['id', 'title', 'status', 'created_at'])
            ->map(fn (ServiceRequest $request): array => [
                'type' => 'request',
                'label' => 'Nouvelle demande',
                'title' => $request->title,
                'status' => $request->status->value,
                'url' => route('client.requests.show', $request),
                'occurred_at' => $request->created_at?->toIso8601String(),
                'detail' => null,
            ]);

        $quotes = Quotation::query()
            ->whereHas('serviceRequest', fn (Builder $query) => $query->where('client_id', $clientId))
            ->with(['serviceRequest.service'])
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'service_request_id', 'status', 'updated_at'])
            ->map(fn (Quotation $quote): array => [
                'type' => 'quote',
                'label' => 'Devis mis à jour',
                'title' => $quote->serviceRequest?->service?->title
                    ?: $quote->serviceRequest?->title
                    ?: 'Devis #'.$quote->getKey(),
                'status' => $quote->status->value,
                'url' => route('client.quotes.show', $quote),
                'occurred_at' => $quote->updated_at?->toIso8601String(),
                'detail' => null,
            ]);

        $orders = Order::query()
            ->where('client_id', $clientId)
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'order_number', 'status', 'updated_at'])
            ->map(fn (Order $order): array => [
                'type' => 'order',
                'label' => 'Commande mise à jour',
                'title' => $order->order_number ?: '#'.$order->getKey(),
                'status' => $order->status->value,
                'url' => route('client.orders.show', $order),
                'occurred_at' => $order->updated_at?->toIso8601String(),
                'detail' => null,
            ]);

        $payments = Payment::query()
            ->where('client_id', $clientId)
            ->with('order:id,order_number')
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'order_id', 'status', 'amount', 'currency', 'paid_at', 'updated_at'])
            ->map(fn (Payment $payment): array => [
                'type' => 'payment',
                'label' => $payment->status === PaymentStatus::SUCCEEDED ? 'Paiement confirmé' : 'Paiement mis à jour',
                'title' => $payment->order?->order_number ?: 'Paiement #'.$payment->getKey(),
                'status' => $payment->status->value,
                'url' => route('client.payments.show', $payment),
                'occurred_at' => ($payment->paid_at ?? $payment->updated_at)?->toIso8601String(),
                'detail' => number_format((float) $payment->amount, 2, ',', ' ').' '.$payment->currency,
            ]);

        $messages = Conversation::query()
            ->where('client_id', $clientId)
            ->whereHas('participants', fn (Builder $participant) => $participant
                ->where('users.id', $clientId)
                ->whereNull('conversation_participants.left_at')
                ->whereNull('conversation_participants.archived_at'))
            ->whereNotNull('last_message_id')
            ->with(['professional.user', 'lastMessage'])
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'professional_id', 'last_message_id', 'updated_at'])
            ->map(fn (Conversation $conversation): array => [
                'type' => 'message',
                'label' => 'Échange récent',
                'title' => $conversation->professional?->business_name
                    ?: $conversation->professional?->user?->name
                    ?: 'Conversation',
                'status' => 'recent',
                'url' => route('client.messages.show', $conversation),
                'occurred_at' => $conversation->lastMessage?->created_at?->toIso8601String(),
                'detail' => Str::limit((string) $conversation->lastMessage?->body, 90),
            ]);

        return $requests
            ->concat($quotes)
            ->concat($orders)
            ->concat($payments)
            ->concat($messages)
            ->filter(fn (array $activity): bool => $activity['occurred_at'] !== null)
            ->sortByDesc('occurred_at')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, detail: string, url: string, type: string}>
     */
    private function recentFavorites(int $clientId): array
    {
        return Favorite::query()
            ->where('user_id', $clientId)
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('professionalProfile', fn (Builder $profile) => $profile->publiclyVisible())
                    ->orWhereHas('service', fn (Builder $service) => $service->requestable());
            })
            ->with([
                'professionalProfile.user:id,name',
                'professionalProfile.user.profile:id,user_id,first_name,last_name',
                'service:id,category_id,professional_profile_id,title,slug,status,published_at',
                'service.category:id,name',
            ])
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(function (Favorite $favorite): ?array {
                if ($favorite->professionalProfile !== null) {
                    $professional = $favorite->professionalProfile;

                    return [
                        'type' => 'professional',
                        'title' => $professional->business_name
                            ?: trim(($professional->user?->profile?->first_name ?? '').' '.($professional->user?->profile?->last_name ?? ''))
                            ?: $professional->user?->name
                            ?: 'Professionnel',
                        'detail' => $professional->professional_title ?: 'Professionnel PROXIWORK',
                        'url' => route('client.professionals.show', $professional),
                    ];
                }

                if ($favorite->service !== null) {
                    return [
                        'type' => 'service',
                        'title' => $favorite->service->title,
                        'detail' => $favorite->service->category?->name ?: 'Service publié',
                        'url' => route('public.services.show', $favorite->service),
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Builder<Conversation>
     */
    private function unreadConversationsQuery(int $clientId): Builder
    {
        return Conversation::query()
            ->where('client_id', $clientId)
            ->whereExists(function ($query) use ($clientId): void {
                $query->selectRaw('1')
                    ->from('messages as unread_messages')
                    ->join('conversation_participants as unread_participants', function ($join) use ($clientId): void {
                        $join->on('unread_participants.conversation_id', '=', 'unread_messages.conversation_id')
                            ->where('unread_participants.user_id', '=', $clientId)
                            ->whereNull('unread_participants.left_at')
                            ->whereNull('unread_participants.archived_at');
                    })
                    ->whereColumn('unread_messages.conversation_id', 'conversations.id')
                    ->where('unread_messages.sender_id', '!=', $clientId)
                    ->whereRaw('unread_messages.id > COALESCE(unread_participants.last_read_message_id, 0)');
            });
    }

    /**
     * @return Collection<int, ProfessionalProfile>
     */
    private function recommendedProfessionals(?Address $address): Collection
    {
        $query = ProfessionalProfile::query()
            ->publiclyVisible()
            ->whereHas('services', fn (Builder $service) => $service->requestable())
            ->with([
                'user:id,name',
                'user.profile:id,user_id,first_name,last_name,avatar_path',
                'skills' => fn (Builder $skills) => $skills->where('status', 'active'),
            ])
            ->withCount(['services as published_services_count' => fn (Builder $service) => $service->requestable()]);

        if (filled($address?->city)) {
            $query->orderByRaw('CASE WHEN LOWER(city) = LOWER(?) THEN 0 ELSE 1 END', [$address->city]);
        }

        if (filled($address?->province)) {
            $query->orderByRaw('CASE WHEN LOWER(province) = LOWER(?) THEN 0 ELSE 1 END', [$address->province]);
        }

        return $query
            ->orderByDesc('rating_average')
            ->orderByDesc('rating_count')
            ->orderByDesc('verified_at')
            ->limit(3)
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    private function recentServices(): Collection
    {
        return Service::query()
            ->requestable()
            ->with([
                'category:id,name',
                'images:id,service_id,path,alt_text,is_cover,sort_order',
                'professionalProfile:id,user_id,business_name,professional_title,verification_status',
                'professionalProfile.user:id,name',
            ])
            ->latest('published_at')
            ->limit(3)
            ->get();
    }
}
