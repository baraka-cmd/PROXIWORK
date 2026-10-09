<?php

declare(strict_types=1);

namespace App\Services\Admin\Payment;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminPaymentManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Payment::query()
            ->with([
                'order:id,order_number,client_id,total,currency,status',
                'client:id,name,email',
            ])
            ->when(
                $filters['search'] ?? null,
                fn ($query, $value) => $query->where(
                    fn ($nested) => $nested
                        ->where('id', $value)
                        ->orWhereHas(
                            'order',
                            fn ($order) => $order->where('order_number', 'like', "%{$value}%"),
                        )
                        ->orWhereHas(
                            'client',
                            fn ($client) => $client
                                ->where('name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%"),
                        ),
                ),
            )
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['provider'] ?? null, fn ($query, $value) => $query->where('provider', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function show(Payment $payment): Payment
    {
        return $payment->load([
            'order.client',
            'order.professional.user',
            'order.items',
            'client',
            'intent',
            'transactions',
            'commission',
        ]);
    }
}
