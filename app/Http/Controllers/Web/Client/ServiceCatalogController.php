<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ServiceRequest\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request, ServiceRequestService $serviceRequestService): View
    {
        $client = $request->user();
        $ownedOrders = Order::query()->where('client_id', $client->getKey());

        return view('client.services.index', [
            'requestCount' => $serviceRequestService->clientRequestStats($client)['total'],
            'activeOrderCount' => (clone $ownedOrders)
                ->whereIn('status', OrderStatus::operationallyActiveValues())
                ->count(),
            'completedOrderCount' => (clone $ownedOrders)
                ->where('status', OrderStatus::COMPLETED->value)
                ->count(),
        ]);
    }
}
