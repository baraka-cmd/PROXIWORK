<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\IndexServiceRequestRequest;
use App\Http\Requests\ServiceRequest\RejectServiceRequestRequest;
use App\Models\Conversation;
use App\Models\ServiceRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\ServiceRequest\ServiceRequestLifecycleService;
use App\Services\ServiceRequest\ServiceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function __construct(
        private readonly ServiceRequestService $serviceRequestService,
        private readonly ServiceRequestLifecycleService $lifecycleService,
        private readonly AuditLogService $auditLogService,
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function index(IndexServiceRequestRequest $request): View
    {
        $filters = $request->filters();
        $requests = $this->serviceRequestService->professionalRequests(
            $request->user(),
            $request->integer('per_page', 15),
            $filters,
        );

        return view('professional.requests.index', compact('requests', 'filters'));
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        $this->authorize('view', $serviceRequest);

        abort_unless(
            $serviceRequest->professional?->user_id === auth()->id(),
            404,
        );

        $serviceRequest->load([
            'service',
            'client',
            'address',
            'addressSnapshot',
            'statusHistories.actor',
            'quotation.currentOffer',
        ]);

        $conversation = Conversation::query()
            ->where('client_id', $serviceRequest->client_id)
            ->where('professional_id', $serviceRequest->professional_id)
            ->first();

        if ($conversation !== null && Gate::allows('view', $conversation) === false) {
            $conversation = null;
        }

        return view('professional.requests.show', compact('serviceRequest', 'conversation'));
    }

    public function reject(
        RejectServiceRequestRequest $request,
        ServiceRequest $serviceRequest,
    ): RedirectResponse {
        $this->authorize('reject', $serviceRequest);

        $serviceRequest = $this->lifecycleService->rejectByProfessional(
            $serviceRequest,
            $request->user(),
            $request->validated('reason'),
        );

        $this->auditLogService->record(
            'service_request_rejected',
            $serviceRequest,
            $request->user(),
            ['reason' => $request->validated('reason')],
            $request,
        );

        $this->notificationService->serviceRequestRejected($serviceRequest->client);

        return redirect()
            ->route('professional.requests.show', $serviceRequest)
            ->with('success', 'La demande a été refusée.');
    }
}
