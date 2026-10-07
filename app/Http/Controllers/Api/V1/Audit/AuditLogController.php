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
            ->with('user:id,name,email')
            ->when(
                $request->filled('actor_id'),
                fn ($query) => $query->where('user_id', $request->integer('actor_id'))
            )
            ->when(
                $request->filled('action'),
                fn ($query) => $query->where('action', $request->string('action'))
            )
            ->when(
                $request->filled('resource_type'),
                fn ($query) => $query->where('subject_type', $request->string('resource_type'))
            )
            ->when(
                $request->filled('resource_id'),
                fn ($query) => $query->where('subject_id', $request->integer('resource_id'))
            )
            ->when(
                $request->filled('ip'),
                fn ($query) => $query->where('ip_address', $request->string('ip'))
            )
            ->when(
                $request->filled('from'),
                fn ($query) => $query->where('created_at', '>=', $request->date('from'))
            )
            ->when(
                $request->filled('to'),
                fn ($query) => $query->where('created_at', '<=', $request->date('to'))
            )
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

        return (new AuditLogResource($auditLog->load('user')))->additional([
            'message' => 'Journal d’audit récupéré avec succès.',
            'meta' => [],
        ]);
    }
}
