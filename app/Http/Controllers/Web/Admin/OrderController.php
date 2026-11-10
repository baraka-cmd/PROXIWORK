<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Order\AdminOrderIndexRequest;
use App\Models\Order;
use App\Models\OrderDeliveryAttachment;
use App\Services\Admin\Order\AdminOrderManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'overview' => $this->orderManager->overview(),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $this->orderManager->show($order),
        ]);
    }

    public function downloadDeliveryAttachment(
        Request $request,
        Order $order,
        OrderDeliveryAttachment $attachment,
    ): BinaryFileResponse {
        abort_unless($request->user()?->hasPermissionTo('orders.view'), 403);
        abort_unless($attachment->order_id === $order->getKey(), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return response()->download(
            $disk->path($attachment->path),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
