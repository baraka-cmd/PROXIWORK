<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                ...array_map(
                    static fn (OrderStatus $status): string => $status->value,
                    OrderStatus::cases(),
                ),
                'active',
            ])],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        if (
            filled($filters['from'] ?? null)
            && filled($filters['to'] ?? null)
            && $filters['to'] < $filters['from']
        ) {
            throw ValidationException::withMessages([
                'to' => 'La date de fin doit être postérieure ou égale à la date de début.',
            ]);
        }

        $selectedStatus = (string) ($filters['status'] ?? '');
        $profile = $request->user()->professionalProfile()->firstOrFail();

        $query = Order::query()
            ->where('professional_id', $profile->getKey())
            ->with(['client:id,name,email', 'items'])
            ->latest();

        if ($selectedStatus === 'active') {
            $query->whereIn('status', OrderStatus::operationallyActiveValues());
        } elseif ($selectedStatus !== '') {
            $status = OrderStatus::tryFrom($selectedStatus);

            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        // Completed orders are filtered by completion date; other views use creation date.
        $dateColumn = $selectedStatus === OrderStatus::COMPLETED->value ? 'completed_at' : 'created_at';
        if (filled($filters['from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }

        $orders = $query->paginate(12)->withQueryString();

        return view('professional.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'selectedStatus' => $selectedStatus,
            'from' => (string) ($filters['from'] ?? ''),
            'to' => (string) ($filters['to'] ?? ''),
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
