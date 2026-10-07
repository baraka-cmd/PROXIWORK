<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Moderation;

use App\Enums\ModerationActionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Moderation\ModerateReportRequest;
use App\Http\Resources\AdminActionResource;
use App\Models\Report;
use App\Services\Admin\Moderation\ModerationService;
use Illuminate\Http\JsonResponse;

class ModerationActionController extends Controller
{
    public function __construct(private ModerationService $service) {}

    public function store(ModerateReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('manage', $report);

        $action = $this->service->act(
            $request->user(),
            $report,
            ModerationActionType::from($request->string('action_type')->toString()),
            $request->input('reason_code'),
            $request->input('note'),
            $request,
        );

        return (new AdminActionResource($action))
            ->additional([
                'message' => 'Action de modération appliquée avec succès.',
                'meta' => [],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
