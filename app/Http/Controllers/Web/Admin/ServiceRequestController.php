<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest\AdminServiceRequestActionRequest;
use App\Http\Requests\Admin\ServiceRequest\AdminServiceRequestIndexRequest;
use App\Models\ServiceRequest;
use App\Services\Admin\ServiceRequest\AdminServiceRequestManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function __construct(private readonly AdminServiceRequestManagementService $requestManager) {}

    public function index(AdminServiceRequestIndexRequest $request): View
    {
        return view('admin.service-requests.index', [
            'requests' => $this->requestManager->paginate($request->validated()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        $this->authorize('adminView', $serviceRequest);
        return view('admin.service-requests.show', ['request' => $this->requestManager->show($serviceRequest)]);
    }

    public function cancel(AdminServiceRequestActionRequest $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('adminManage', $serviceRequest);
        $this->requestManager->cancel($serviceRequest, $request->user(), $request->validated('reason'));
        return back()->with('success', 'La demande a été annulée administrativement.');
    }

    public function reject(AdminServiceRequestActionRequest $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('adminManage', $serviceRequest);
        $this->requestManager->reject($serviceRequest, $request->user(), $request->validated('reason'));
        return back()->with('success', 'La demande a été rejetée administrativement.');
    }
}
