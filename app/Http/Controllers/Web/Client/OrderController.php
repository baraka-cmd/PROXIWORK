<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexClientOrdersRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProfessionalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(IndexClientOrdersRequest $request): View
    {
        $clientId = $request->user()->getKey();
        $filters = $request->validated();

        // All statistics and options are derived from this client's own orders.
        $ownedOrders = Order::query()->where('client_id', $clientId);

        $statistics = [
            'total' => (clone $ownedOrders)->count(),
            'active' => (clone $ownedOrders)
                ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::IN_PROGRESS->value])
                ->count(),
            'pending_payment' => (clone $ownedOrders)
                ->where('status', OrderStatus::PENDING_PAYMENT->value)
                ->count(),
            'completed' => (clone $ownedOrders)
                ->where('status', OrderStatus::COMPLETED->value)
                ->count(),
        ];

        $query = (clone $ownedOrders)->with([
            'professional.user',
            'items',
            'payment',
        ]);

        if (filled($filters['bucket'] ?? null)) {
            match ($filters['bucket']) {
                'active' => $query->whereIn('status', [
                    OrderStatus::CONFIRMED->value,
                    OrderStatus::IN_PROGRESS->value,
                ]),
                'pending_payment' => $query->where('status', OrderStatus::PENDING_PAYMENT->value),
                'completed' => $query->where('status', OrderStatus::COMPLETED->value),
                default => null,
            };
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['payment_status'] ?? null)) {
            $query->whereHas('payment', fn ($paymentQuery) => $paymentQuery
                ->where('status', $filters['payment_status']));
        }

        if (filled($filters['professional_id'] ?? null)) {
            $query->where('professional_id', $filters['professional_id']);
        }

        if (filled($filters['service_id'] ?? null)) {
            $query->whereHas('items', fn ($itemQuery) => $itemQuery
                ->where('service_id', $filters['service_id']));
        }

        if (filled($filters['created_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (filled($filters['created_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (filled($filters['q'] ?? null)) {
            $term = $filters['q'];
            $numericId = preg_match('/^\d+$/', $term) === 1 ? (int) $term : null;

            $query->where(function ($searchQuery) use ($term, $numericId): void {
                $searchQuery->where('order_number', 'like', '%'.$term.'%')
                    ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                        ->where('service_title', 'like', '%'.$term.'%'))
                    ->orWhereHas('professional.user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', '%'.$term.'%'));

                if ($numericId !== null && $numericId > 0) {
                    $searchQuery->orWhere('orders.id', $numericId);
                }
            });
        }

        $sort = $filters['sort'] ?? 'newest';

        if ($sort === 'oldest') {
            $query->orderBy('created_at')->orderBy('id');
        } elseif ($sort === 'amount_asc') {
            $query->orderBy('total')->orderByDesc('id');
        } elseif ($sort === 'amount_desc') {
            $query->orderByDesc('total')->orderByDesc('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $orders = $query->paginate(12)->withQueryString();

        $professionals = ProfessionalProfile::query()
            ->whereIn('id', (clone $ownedOrders)->select('professional_id'))
            ->with('user:id,name')
            ->orderBy('professional_title')
            ->get(['id', 'user_id', 'professional_title']);

        $services = OrderItem::query()
            ->whereIn('order_id', (clone $ownedOrders)->select('id'))
            ->whereNotNull('service_id')
            ->select('service_id', 'service_title')
            ->distinct()
            ->orderBy('service_title')
            ->get();

        $statusLabels = [
            OrderStatus::PENDING_PAYMENT->value => 'En attente de paiement',
            OrderStatus::CONFIRMED->value => 'Confirmée / planifiée',
            OrderStatus::IN_PROGRESS->value => 'En cours',
            OrderStatus::COMPLETED->value => 'Terminée',
            OrderStatus::CANCELLED->value => 'Annulée',
            OrderStatus::DISPUTED->value => 'Contestée',
        ];

        $paymentStatusLabels = [
            PaymentStatus::INITIATED->value => 'Initialisé',
            PaymentStatus::PENDING->value => 'En attente',
            PaymentStatus::PROCESSING->value => 'En traitement',
            PaymentStatus::SUCCEEDED->value => 'Réussi',
            PaymentStatus::FAILED->value => 'Échoué',
            PaymentStatus::CANCELLED->value => 'Annulé',
            PaymentStatus::EXPIRED->value => 'Expiré',
            PaymentStatus::REFUNDED->value => 'Remboursé',
        ];

        return view('client.orders.index', compact(
            'orders',
            'filters',
            'statistics',
            'professionals',
            'services',
            'statusLabels',
            'paymentStatusLabels',
        ));
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load([
            'professional.user',
            'items',
            'serviceRequest.service',
            'quotation',
            'acceptedOffer',
            'statusHistories',
            'addressSnapshot',
            'payment.transactions',
        ]);

        return view('client.orders.show', compact('order'));
    }

    public function pay(Order $order): View|RedirectResponse
    {
        Gate::authorize('pay', $order);

        if ($order->status !== OrderStatus::PENDING_PAYMENT) {
            return redirect()->route('client.orders.show', $order)
                ->withErrors(['payment' => 'Cette commande n’accepte plus de paiement.']);
        }

        $order->load('items');

        return view('client.orders.payment', [
            'order' => $order,
            'methods' => PaymentMethod::cases(),
            'providers' => PaymentProvider::cases(),
        ]);
    }
}
