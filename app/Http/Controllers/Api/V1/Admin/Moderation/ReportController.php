<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Moderation;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Moderation\{AssignReportRequest,ResolveReportRequest,StartReportReviewRequest,StoreReportRequest};
use App\Http\Resources\Admin\Moderation\ReportResource;
use App\Models\{Report,User};
use App\Services\Admin\Moderation\ModerationService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ModerationService $service) {}

    public function store(StoreReportRequest $request): ReportResource
    {
        $this->authorize('create', Report::class);
        $report = $this->service->createReport($request->user(), $request->string('target_type')->toString(), $request->integer('target_id'), $request->string('reason_code')->toString(), $request->input('description'), $request);
        return (new ReportResource($report))->additional(['message'=>'Signalement créé avec succès.','meta'=>[]]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Report::class);
        $perPage = min(max($request->integer('per_page',25),1),100);
        $query = Report::query()->with(['reporter:id,name,email','assignee:id,name','resolver:id,name','target'])
            ->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))
            ->when($request->filled('priority'),fn($q)=>$q->where('priority',$request->string('priority')))
            ->when($request->filled('target_type'),fn($q)=>$q->where('target_type',$request->string('target_type')))->latest('id');
        return ReportResource::collection($query->paginate($perPage)->withQueryString())->additional(['message'=>'Signalements récupérés avec succès.','meta'=>[]]);
    }

    public function show(Report $report): ReportResource
    {
        $this->authorize('view',$report);
        return (new ReportResource($report->load(['reporter','assignee','resolver','target','moderationActions.moderator'])))->additional(['message'=>'Signalement récupéré avec succès.','meta'=>[]]);
    }

    public function assign(AssignReportRequest $request, Report $report): ReportResource
    {
        $this->authorize('manage',$report);
        $result=$this->service->assign($request->user(),$report,User::findOrFail($request->integer('assigned_to')),$request);
        return (new ReportResource($result))->additional(['message'=>'Signalement assigné avec succès.','meta'=>[]]);
    }

    public function startReview(StartReportReviewRequest $request, Report $report): ReportResource
    {
        $this->authorize('manage',$report);
        return new ReportResource($this->service->start($request->user(),$report,$request));
    }

    public function resolve(ResolveReportRequest $request, Report $report): ReportResource
    {
        $this->authorize('manage',$report);
        $status=ReportStatus::from($request->string('status')->toString());
        return new ReportResource($this->service->finish($request->user(),$report,$status,$request->input('resolution_note'),$request));
    }
}
