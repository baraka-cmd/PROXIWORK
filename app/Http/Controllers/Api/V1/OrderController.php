<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): OrderResource
    {
        $order->load([
            'professional.user',
            'items',
            'addressSnapshot',
            'statusHistories',
        ]);

        $this->authorize('view', $order);

        return new OrderResource($order);
    }
}
