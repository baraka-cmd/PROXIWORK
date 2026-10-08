<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Order\AdminOrderIndexRequest;
use App\Models\Order;
use App\Services\Admin\Order\AdminOrderManagementService;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly AdminOrderManagementService $orderManager,
    ) {}

    public function index(AdminOrderIndexRequest $request): View
    {
        return view('admin.orders.index', [
            'orders' => $this->orderManager->paginate($request->validated()),
            'filters' => $request->validated(),
            'statuses' => $this->orderManager->statusOptions(),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $this->orderManager->show($order),
        ]);
    }
}
