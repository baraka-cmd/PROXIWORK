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
                'order:id,order_number,client_id,professional_id,total,currency,status',
                'order.professional:id,user_id',
                'order.professional.user:id,name',
                'client:id,name,email',
            ])
            ->when($filters['search'] ?? null, function ($query, string $value): void {
                $query->where(function ($nested) use ($value): void {
                    if (ctype_digit($value)) {
                        $nested->orWhere('id', (int) $value);
                    }

                    $nested->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$value}%"))
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%"))
                        ->orWhereHas('order.professional.user', fn ($user) => $user->where('name', 'like', "%{$value}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['transaction_status'] ?? null, fn ($query, $value) => $query->whereHas('transactions', fn ($transactions) => $transactions->where('status', $value)))
            ->when($filters['provider'] ?? null, fn ($query, $value) => $query->where('provider', $value))
            ->when($filters['currency'] ?? null, fn ($query, $value) => $query->where('currency', strtoupper($value)))
            ->when($filters['min_amount'] ?? null, fn ($query, $value) => $query->where('amount', '>=', $value))
            ->when($filters['max_amount'] ?? null, fn ($query, $value) => $query->where('amount', '<=', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('created_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function show(Payment $payment): Payment
    {
        return $payment->load([
            'order.client',
            'order.professional.user',
            'client',
            'transactions',
            'events',
            'commission',
        ]);
    }
}
