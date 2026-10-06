<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SkillStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Skill\IndexSkillRequest;
use App\Http\Requests\Skill\StoreSkillRequest;
use App\Http\Requests\Skill\UpdateSkillRequest;
use App\Http\Resources\Skill\SkillAdminResource;
use App\Http\Resources\Skill\SkillResource;
use App\Models\Skill;
use App\Services\Audit\AuditLogService;
use App\Services\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillService $skillService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(IndexSkillRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $skills = Skill::query()
            ->where('status', SkillStatus::ACTIVE->value)
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return SkillResource::collection($skills)->additional([
            'message' => 'Compétences récupérées avec succès.',
            'meta' => ['scope' => 'active'],
        ]);
    }

    public function show(Skill $skill): SkillResource
    {
        abort_if($skill->status !== SkillStatus::ACTIVE, 404);
        return (new SkillResource($skill))->additional([
            'message' => 'Compétence récupérée avec succès.',
            'meta' => [],
        ]);
    }

    public function adminIndex(IndexSkillRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Skill::class);
        $validated = $request->validated();

        $skills = Skill::query()
            ->when(array_key_exists('status', $validated), fn ($query) => $query->where('status', SkillStatus::from($validated['status'])->value))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return SkillAdminResource::collection($skills)->additional([
            'message' => 'Compétences administratives récupérées avec succès.',
            'meta' => ['scope' => 'admin'],
        ]);
    }

    public function adminShow(Skill $skill): SkillAdminResource
    {
        $this->authorize('view', $skill);
        return (new SkillAdminResource($skill))->additional(['message' => 'Compétence administrative récupérée avec succès.', 'meta' => []]);
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        $skill = $this->skillService->create($request->validated());
        $this->auditLogService->record('skill_created', $skill, $request->user(), [], $request);

        return (new SkillAdminResource($skill))->additional(['message' => 'Compétence créée avec succès.', 'meta' => []])
            ->response()->setStatusCode(201);
    }

    public function update(UpdateSkillRequest $request, Skill $skill): SkillAdminResource
    {
        $this->authorize('update', $skill);
        $skill = $this->skillService->update($skill, $request->validated());
        $this->auditLogService->record('skill_updated', $skill, $request->user(), [], $request);

        return (new SkillAdminResource($skill))->additional(['message' => 'Compétence mise à jour avec succès.', 'meta' => []]);
    }

    public function destroy(Request $request, Skill $skill): Response
    {
        $this->authorize('delete', $skill);
        $this->skillService->archive($skill);
        $this->auditLogService->record('skill_archived', $skill, $request->user(), [], $request);
        return response()->noContent();
    }
}
