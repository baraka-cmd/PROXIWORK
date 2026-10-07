<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin\Moderation;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Moderation\{AssignReportRequest,ResolveReportRequest,StartReportReviewRequest,StoreReportRequest};
use App\Http\Resources\Admin\Moderation\ReportResource;
use App\Models\Report;
use App\Services\Admin\Moderation\ModerationService;
use Illuminate\Http\Request;
class ReportController extends Controller {
 public function __construct(private ModerationService $service){}
 public function store(StoreReportRequest $request):ReportResource { $this->authorize('create',Report::class); $report=$this->service->createReport($request->user(),$request->string('target_type')->toString(),$request->integer('target_id'),$request->string('reason_code')->toString(),$request->input('description'),$request); return (new ReportResource($report))->additional(['message'=>'Signalement créé avec succès.','meta'=>[]]); }
 public function index(Request $request) { $this->authorize('viewAny',Report::class); $per=min(max($request->integer('per_page',25),1),100); $q=Report::query()->with(['reporter:id,name,email','assignee:id,name','resolver:id,name','target'])->when($request->filled('status'),fn($x)=>$x->where('status',$request->string('status')))->when($request->filled('priority'),fn($x)=>$x->where('priority',$request->string('priority')))->when($request->filled('target_type'),fn($x)=>$x->where('target_type',$request->string('target_type')))->latest('id'); return ReportResource::collection($q->paginate($per)->withQueryString())->additional(['message'=>'Signalements récupérés avec succès.','meta'=>[]]); }
 public function show(Report $report):ReportResource { $this->authorize('view',$report); return (new ReportResource($report->load(['reporter','assignee','resolver','target','moderationActions.moderator'])))->additional(['message'=>'Signalement récupéré avec succès.','meta'=>[]]); }
 public function assign(AssignReportRequest $request,Report $report):ReportResource { $this->authorize('manage',$report); $assignee=AppModelsUser::findOrFail($request->integer('assigned_to')); $result=$this->service->assign($request->user(),$report,$assignee,$request); return (new ReportResource($result))->additional(['message'=>'Signalement assigné avec succès.','meta'=>[]]); }
 public function startReview(StartReportReviewRequest $request,Report $report):ReportResource { $this->authorize('manage',$report); $result=$this->service->start($request->user(),$report,$request); return (new ReportResource($result))->additional(['message'=>'Signalement placé en revue.','meta'=>[]]); }
 public function resolve(ResolveReportRequest $request,Report $report):ReportResource { $this->authorize('manage',$report); $status=ReportStatus::from($request->string('status')->toString()); $result=$this->service->finish($request->user(),$report,$status,$request->input('resolution_note'),$request); return (new ReportResource($result))->additional(['message'=>'Signalement clôturé avec succès.','meta'=>[]]); }
}
