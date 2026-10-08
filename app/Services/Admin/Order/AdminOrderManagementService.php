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
        return Order::query()->with(['client:id,name,email','professional.user:id,name,email','serviceRequest:id,title','payment:id,order_id,status,amount,currency,paid_at'])
            ->when($filters['search']??null,fn($q,$v)=>$q->where(fn($x)=>$x->where('order_number','like',"%$v%")->orWhereHas('client',fn($u)=>$u->where('name','like',"%$v%")->orWhere('email','like',"%$v%"))))
            ->when($filters['status']??null,fn($q,$v)=>$q->where('status',$v))
            ->when($filters['payment_status']??null,fn($q,$v)=>$q->whereHas('payment',fn($p)=>$p->where('status',$v)))
            ->when($filters['from']??null,fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($filters['to']??null,fn($q,$v)=>$q->whereDate('created_at','<=',$v))
            ->orderByDesc('created_at')->paginate($filters['per_page']??20)->withQueryString();
    }

    public function show(Order $order): Order
    {
        return $order->load(['client:id,name,email','professional.user:id,name,email','serviceRequest.service','quotation','acceptedOffer','items','addressSnapshot','statusHistories','payment.transactions','payment.intent','commission','review']);
    }

    public function statusOptions(): array
    {
        return array_map(fn(OrderStatus $s)=>$s->value,OrderStatus::cases());
    }
}
