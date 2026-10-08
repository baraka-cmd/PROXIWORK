<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->professionalProfile()->firstOrFail();

        $query = Order::query()
            ->where('professional_id', $profile->getKey())
            ->with(['client:id,name,email', 'items'])
            ->latest();

        if ($request->filled('status')) {
            $status = OrderStatus::tryFrom((string) $request->string('status'));

            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        $orders = $query->paginate(12)->withQueryString();

        return view('professional.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'selectedStatus' => $request->string('status')->toString(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        abort_unless(
            $order->professional_id === $request->user()->professionalProfile?->getKey(),
            404
        );

        $order->load([
            'client:id,name,email',
            'items',
            'serviceRequest.service',
            'quotation',
            'acceptedOffer',
            'statusHistories',
            'addressSnapshot',
            'payment.transactions',
            'commission',
            'review',
        ]);

        return view('professional.orders.show', compact('order'));
    }
}
