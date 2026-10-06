<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        $logs = AuditLog::query()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return AuditLogResource::collection($logs)->additional([
            'message' => 'Journaux d’audit récupérés avec succès.',
            'meta' => [],
        ]);
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        $this->authorize('view', $auditLog);

        return (new AuditLogResource($auditLog))->additional([
            'message' => 'Journal d’audit récupéré avec succès.',
            'meta' => [],
        ]);
    }
}
