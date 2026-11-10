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
        $query = Order::query()
            ->with([
                'client:id,name,email',
                'professional.user:id,name,email',
                'serviceRequest:id,title,service_id',
                'serviceRequest.service:id,title,category_id',
                'payment:id,order_id,status,amount,currency,paid_at',
            ])
            ->withExists([
                'statusHistories as has_work_blocker' => fn ($history) => $history
                    ->whereJsonContains('metadata->event', 'work_blocked'),
            ])
            ->when($filters['search'] ?? null, function ($query, string $value): void {
                $query->where(function ($nested) use ($value): void {
                    $nested->where('order_number', 'like', "%{$value}%")
                        ->orWhereHas('client', fn ($client) => $client
                            ->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%"))
                        ->orWhereHas('professional.user', fn ($professional) => $professional
                            ->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['payment_status'] ?? null, fn ($query, $value) => $query->whereHas(
                'payment',
                fn ($payment) => $payment->where('status', $value),
            ))
            ->when($filters['service_id'] ?? null, fn ($query, $value) => $query->whereHas(
                'serviceRequest',
                fn ($request) => $request->where('service_id', $value),
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->whereHas(
                'serviceRequest.service',
                fn ($service) => $service->where('category_id', $value),
            ))
            ->when(array_key_exists('has_incident', $filters) && $filters['has_incident'] !== '', function ($query) use ($filters): void {
                $constraint = fn ($history) => $history->whereJsonContains('metadata->event', 'work_blocked');
                $query->{$filters['has_incident'] ? 'whereHas' : 'whereDoesntHave'}('statusHistories', $constraint);
            })
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value));

        $sorts = [
            'oldest' => ['created_at', 'asc'],
            'amount_desc' => ['total', 'desc'],
            'amount_asc' => ['total', 'asc'],
            'newest' => ['created_at', 'desc'],
        ];
        [$column, $direction] = $sorts[$filters['sort'] ?? 'newest'] ?? $sorts['newest'];

        return $query
            ->orderBy($column, $direction)
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    /**
     * Operational indicators are derived from authoritative order and history records.
     * A blocker is an event, not a new order status.
     *
     * @return array<string, int>
     */
    public function overview(): array
    {
        $counts = [];
        foreach (OrderStatus::cases() as $status) {
            $counts[$status->value] = Order::query()->where('status', $status)->count();
        }

        $counts['work_blocked'] = Order::query()
            ->whereHas('statusHistories', fn ($history) => $history->whereJsonContains('metadata->event', 'work_blocked'))
            ->count();

        $counts['payment_succeeded_not_started'] = Order::query()
            ->where('status', OrderStatus::CONFIRMED)
            ->whereHas('payment', fn ($payment) => $payment->where('status', 'succeeded'))
            ->whereNull('started_at')
            ->count();

        return $counts;
    }

    public function show(Order $order): Order
    {
        return $order->load([
            'client:id,name,email',
            'professional.user:id,name,email',
            'serviceRequest.service.category',
            'quotation.offers.creator',
            'quotation.events.actor',
            'acceptedOffer',
            'items',
            'addressSnapshot',
            'statusHistories.changedBy:id,name,email',
            'deliveryAttachments.submitter:id,name,email',
            'payment.transactions',
            'payment.events',
            'payment.refunds',
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
