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
            ->when($filters['client_id'] ?? null, fn ($query, $value) => $query->where('client_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when(
                collect([
                    $filters['transaction_status'] ?? null,
                    $filters['transaction_from'] ?? null,
                    $filters['transaction_to'] ?? null,
                    $filters['processed_from'] ?? null,
                    $filters['processed_to'] ?? null,
                ])->contains(fn ($value) => $value !== null && $value !== ''),
                fn ($query) => $query->whereHas('transactions', function ($transactions) use ($filters): void {
                    if (! empty($filters['transaction_status'])) {
                        $transactions->where('status', $filters['transaction_status']);
                    }

                    if (! empty($filters['transaction_from'])) {
                        $transactions->whereDate('created_at', '>=', $filters['transaction_from']);
                    }

                    if (! empty($filters['transaction_to'])) {
                        $transactions->whereDate('created_at', '<=', $filters['transaction_to']);
                    }

                    if (! empty($filters['processed_from']) || ! empty($filters['processed_to'])) {
                        $transactions->where('status', 'succeeded');

                        if (! empty($filters['processed_from'])) {
                            $transactions->whereDate('processed_at', '>=', $filters['processed_from']);
                        }

                        if (! empty($filters['processed_to'])) {
                            $transactions->whereDate('processed_at', '<=', $filters['processed_to']);
                        }
                    }
                }),
            )
            ->when(($filters['refund_status_group'] ?? null) === 'open', fn ($query) => $query->whereHas('refunds', fn ($refunds) => $refunds->whereIn('status', ['requested', 'processing'])))
            ->when($filters['commission_status'] ?? null, fn ($query, $value) => $query->whereHas('commission', fn ($commission) => $commission->where('status', $value)))
            ->when($filters['commission_from'] ?? null, fn ($query, $value) => $query->whereHas('commission', fn ($commission) => $commission->whereDate('posted_at', '>=', $value)))
            ->when($filters['commission_to'] ?? null, fn ($query, $value) => $query->whereHas('commission', fn ($commission) => $commission->whereDate('posted_at', '<=', $value)))
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
