<?php

declare(strict_types=1);

namespace App\Services\Admin\Payment;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminPaymentManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Payment::query()->with(['order:id,order_number,client_id,total,currency,status','client:id,name,email'])
            ->when($filters['search']??null,fn($q,$v)=>$q->where(fn($x)=>$x->where('id',$v)->orWhereHas('order',fn($o)=>$o->where('order_number','like',"%$v%"))->orWhereHas('client',fn($u)=>$u->where('name','like',"%$v%")->orWhere('email','like',"%$v%"))))
            ->when($filters['status']??null,fn($q,$v)=>$q->where('status',$v))
            ->when($filters['provider']??null,fn($q,$v)=>$q->where('provider',$v))
            ->when($filters['from']??null,fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($filters['to']??null,fn($q,$v)=>$q->whereDate('created_at','<=',$v))
            ->orderByDesc('created_at')->paginate($filters['per_page']??20)->withQueryString();
    }

    public function show(Payment $payment): Payment
    {
        return $payment->load(['order.client','order.professional.user','order.items','client','intent','transactions','commission']);
    }
}
