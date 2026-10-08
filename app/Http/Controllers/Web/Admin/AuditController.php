<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Audit\AdminAuditIndexRequest;
use App\Models\AuditLog;
use App\Services\Admin\Audit\AdminAuditService;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __construct(
        private readonly AdminAuditService $auditService,
    ) {}

    public function index(AdminAuditIndexRequest $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        return view('admin.audit.index', [
            'logs' => $this->auditService->paginate($request->validated()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        return view('admin.audit.show', [
            'log' => $this->auditService->show($auditLog),
        ]);
    }
}
