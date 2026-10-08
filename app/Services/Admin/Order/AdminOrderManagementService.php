<?php

declare(strict_types=1);

namespace App\Services\Admin\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminOrderManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'client:id,name,email',
                'professional.user:id,name,email',
                'serviceRequest:id,title',
                'payment:id,order_id,status,amount,currency,paid_at',
            ])
            ->when(
                $filters['search'] ?? null,
                fn ($query, $value) => $query->where(
                    fn ($nested) => $nested
                        ->where('order_number', 'like', "%{$value}%")
                        ->orWhereHas(
                            'client',
                            fn ($client) => $client
                                ->where('name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%"),
                        ),
                ),
            )
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when(
                $filters['payment_status'] ?? null,
                fn ($query, $value) => $query->whereHas(
                    'payment',
                    fn ($payment) => $payment->where('status', $value),
                ),
            )
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function show(Order $order): Order
    {
        return $order->load([
            'client:id,name,email',
            'professional.user:id,name,email',
            'serviceRequest.service',
            'quotation',
            'acceptedOffer',
            'items',
            'addressSnapshot',
            'statusHistories',
            'payment.transactions',
            'payment.intent',
            'commission',
            'review',
        ]);
    }

    public function statusOptions(): array
    {
        return array_map(
            fn (OrderStatus $status): string => $status->value,
            OrderStatus::cases(),
        );
    }
}
