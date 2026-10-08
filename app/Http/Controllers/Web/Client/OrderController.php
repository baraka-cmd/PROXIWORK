<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(\n        private readonly OrderService $orders,\n    ) {\n        // Dependencies are injected only.\n    }

    public function index(Request $request)
    {
        $query = Order::query()
            ->where('client_id', $request->user()->getKey())
            ->with(['professional.user', 'items', 'payment'])
            ->latest();

        if (($status = $request->string('status')->toString()) !== '') {
            if ($enum = OrderStatus::tryFrom($status)) {
                $query->where('status', $enum);
            }
        }

        return view('client.orders.index', [
            'orders' => $query->paginate(12)->withQueryString(),
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);
        $order->load(['professional.user', 'items', 'serviceRequest.service', 'acceptedOffer', 'statusHistories', 'addressSnapshot', 'payment.transactions']);

        return view('client.orders.show', compact('order'));
    }

    public function pay(Order $order)
    {
        Gate::authorize('pay', $order);

        if ($order->status !== OrderStatus::PENDING_PAYMENT) {
            return redirect()->route('client.orders.show', $order)->withErrors(['payment' => 'Cette commande n’accepte plus de paiement.']);
        }

        $order->load('items');

        return view('client.orders.payment', [
            'order' => $order,
            'methods' => PaymentMethod::cases(),
            'providers' => PaymentProvider::cases(),
        ]);
    }
}
