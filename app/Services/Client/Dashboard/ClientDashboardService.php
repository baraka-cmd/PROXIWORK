<?php

declare(strict_types=1);

namespace App\Services\Client\Dashboard;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Conversation;
use App\Models\Address;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ClientDashboardService
{
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

        $favoritesCount = $user->favorites()->count();
        $unreadNotifications = $user->unreadNotifications()->count();
        $clientId = $user->getKey();

        $activeRequests = ServiceRequest::query()
            ->where('client_id', $clientId)
            ->whereIn('status', [
                ServiceRequestStatus::REQUESTED,
                ServiceRequestStatus::QUOTED,
                ServiceRequestStatus::ACCEPTED,
            ]);
        $pendingQuotes = Quotation::query()
            ->whereIn('status', [QuotationStatus::SENT, QuotationStatus::NEGOTIATING])
            ->whereHas('serviceRequest', fn (Builder $query) => $query->where('client_id', $clientId));
        $activeOrders = Order::query()
            ->where('client_id', $clientId)
            ->whereIn('status', [
                OrderStatus::PENDING_PAYMENT,
                OrderStatus::CONFIRMED,
                OrderStatus::IN_PROGRESS,
                OrderStatus::DISPUTED,
            ]);
        $actionPayments = Payment::query()
            ->where('client_id', $clientId)
            ->whereIn('status', [PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED]);
        $unreadMessages = Message::query()
            ->where('sender_id', '!=', $clientId)
            ->whereHas('conversation', fn (Builder $query) => $query->where('client_id', $clientId))
            ->whereExists(function ($query) use ($clientId): void {
                $query->selectRaw('1')
                    ->from('conversation_participants as dashboard_participant')
                    ->whereColumn('dashboard_participant.conversation_id', 'messages.conversation_id')
                    ->where('dashboard_participant.user_id', $clientId)
                    ->whereNull('dashboard_participant.left_at')
                    ->whereRaw('messages.id > COALESCE(dashboard_participant.last_read_message_id, 0)');
            });

        return [
            'profile' => [
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'details' => $user->profile?->only([
                    'first_name',
                    'last_name',
                    'phone',
                    'bio',
                    'locale',
                    'timezone',
                ]),
            ],
            'addresses' => [
                'total' => $addressesCount,
                'default' => $defaultAddress?->toArray(),
            ],
            'favorites' => [
                'count' => $favoritesCount,
            ],
            'notifications' => [
                'unread' => $unreadNotifications,
            ],
            'stats' => [
                'requests' => $activeRequests->count(),
                'quotes' => $pendingQuotes->count(),
                'orders' => $activeOrders->count(),
                'payments' => $actionPayments->count(),
                'messages' => $unreadMessages->count(),
            ],
            'chart' => $this->activityChart($clientId),
            'recent_activity' => $this->recentActivity($clientId),
            'pending_actions' => $this->pendingActions(
                $user->profile !== null,
                $addressesCount,
                $unreadNotifications,
                $pendingQuotes->count(),
                $actionPayments->count(),
                $unreadMessages->count(),
            ),
        ];
    }

    private function pendingActions(
        bool $hasProfile,
        int $addressesCount,
        int $unreadNotifications,
        int $pendingQuotes,
        int $actionPayments,
        int $unreadMessages,
    ): array {
        $actions = [];

        if (! $hasProfile) {
            $actions[] = [
                'type' => 'profile',
                'priority' => 'normal',
                'message' => 'Complétez votre profil.',
            ];
        }

        if ($addressesCount === 0) {
            $actions[] = [
                'type' => 'addresses',
                'priority' => 'normal',
                'message' => 'Ajoutez votre adresse principale.',
            ];
        }

        if ($unreadNotifications > 0) {
            $actions[] = [
                'type' => 'notifications',
                'priority' => 'normal',
                'message' => 'Vous avez des notifications non lues.',
            ];
        }

        if ($pendingQuotes > 0) {
            $actions[] = [
                'type' => 'quotes',
                'priority' => 'high',
                'message' => 'Vous avez des devis à examiner.',
            ];
        }

        if ($actionPayments > 0) {
            $actions[] = [
                'type' => 'payments',
                'priority' => 'high',
                'message' => 'Un paiement nécessite votre attention.',
            ];
        }

        if ($unreadMessages > 0) {
            $actions[] = [
                'type' => 'messages',
                'priority' => 'normal',
                'message' => 'Vous avez de nouveaux messages.',
            ];
        }

        return $actions;
    }

    private function activityChart(int $clientId): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->mapWithKeys(function (int $offset) use ($start): array {
            $date = $start->addMonths($offset);

            return [$date->format('Y-m') => [
                'label' => $date->translatedFormat('M Y'),
                'requests' => 0,
                'orders' => 0,
            ]];
        });

        ServiceRequest::query()
            ->where('client_id', $clientId)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->each(function (ServiceRequest $request) use (&$months): void {
                $key = $request->created_at->format('Y-m');
                if ($months->has($key)) {
                    $months[$key]['requests']++;
                }
            });

        Order::query()
            ->where('client_id', $clientId)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->each(function (Order $order) use (&$months): void {
                $key = $order->created_at->format('Y-m');
                if ($months->has($key)) {
                    $months[$key]['orders']++;
                }
            });

        return $months->values()->all();
    }

    private function recentActivity(int $clientId): array
    {
        $requests = ServiceRequest::query()
            ->where('client_id', $clientId)
            ->latest()
            ->limit(3)
            ->get(['id', 'title', 'status', 'created_at'])
            ->map(fn (ServiceRequest $request): array => [
                'type' => 'request',
                'label' => 'Demande de service',
                'title' => $request->title,
                'status' => $request->status->value,
                'url' => route('client.requests.show', $request),
                'created_at' => $request->created_at?->toISOString(),
            ]);

        $orders = Order::query()
            ->where('client_id', $clientId)
            ->latest()
            ->limit(3)
            ->get(['id', 'order_number', 'status', 'created_at'])
            ->map(fn (Order $order): array => [
                'type' => 'order',
                'label' => 'Commande',
                'title' => $order->order_number ?: '#'.$order->id,
                'status' => $order->status->value,
                'url' => route('client.orders.show', $order),
                'created_at' => $order->created_at?->toISOString(),
            ]);

        return $requests->concat($orders)
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->all();
    }
}
