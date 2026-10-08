<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ModerationActionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Report\AdminReportActionRequest;
use App\Http\Requests\Admin\Report\AdminReportAssignRequest;
use App\Http\Requests\Admin\Report\AdminReportIndexRequest;
use App\Http\Requests\Admin\Report\AdminReportResolveRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\Report\AdminReportManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly AdminReportManagementService $reportService,
    ) {}

    public function index(AdminReportIndexRequest $request): View
    {
        return view('admin.reports.index', [
            'reports' => $this->reportService->paginate($request->validated()),
            'filters' => $request->validated(),
            'assignees' => $this->reportService->assignableUsers(),
        ]);
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        return view('admin.reports.show', [
            'report' => $this->reportService->show($report),
            'assignees' => $this->reportService->assignableUsers(),
        ]);
    }

    public function assign(AdminReportAssignRequest $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);

        $assignee = User::query()->findOrFail($request->validated('assigned_to'));
        $this->reportService->assign($report, $assignee, $request->user(), $request);

        return back()->with('success', 'Le signalement a été assigné.');
    }

    public function startReview(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);
        $this->reportService->startReview($report, $request->user(), $request);

        return back()->with('success', 'Le signalement est maintenant en cours de revue.');
    }

    public function moderate(AdminReportActionRequest $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);

        $this->reportService->moderate(
            $report,
            ModerationActionType::from($request->validated('action_type')),
            $request->user(),
            $request,
            $request->validated('reason_code'),
            $request->validated('note'),
        );

        return back()->with('success', 'L’action de modération a été appliquée.');
    }

    public function resolve(AdminReportResolveRequest $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);
        $this->reportService->resolve($report, $request->validated('resolution_note'), $request->user(), $request);

        return back()->with('success', 'Le signalement a été résolu.');
    }

    public function dismiss(AdminReportResolveRequest $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);
        $this->reportService->dismiss($report, $request->validated('resolution_note'), $request->user(), $request);

        return back()->with('success', 'Le signalement a été classé sans suite.');
    }
}