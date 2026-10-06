<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\IndexServiceRequest;
use App\Http\Requests\ProfessionalService\StoreServiceRequest;
use App\Http\Requests\ProfessionalService\UpdateServiceRequest;
use App\Http\Resources\ProfessionalService\ServiceResource;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Services\Audit\AuditLogService;
use App\Services\ProfessionalService\ProfessionalServiceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalServiceController extends Controller
{
    public function __construct(
        private readonly ProfessionalServiceManager $serviceManager,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(IndexServiceRequest $request): AnonymousResourceCollection
    {
        $profile = $this->professionalProfile($request);
        $validated = $request->validated();

        $services = $profile->services()
            ->with(['category', 'skills', 'images'])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['skill_id'] ?? null, fn ($query, int $skillId) => $query->whereHas('skills', fn ($skills) => $skills->whereKey($skillId)))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return ServiceResource::collection($services)->additional([
            'message' => 'Vos services ont été récupérés avec succès.',
            'meta' => ['scope' => 'professional'],
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $profile = $this->professionalProfile($request);
        $service = $this->serviceManager->create($profile, $request->validated());

        $this->auditLogService->record('service_created', $service, $request->user(), [], $request);

        return (new ServiceResource($service))->additional([
            'message' => 'Service créé avec succès.',
            'meta' => [],
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Service $service): ServiceResource
    {
        $this->authorize('view', $service);

        return (new ServiceResource($service->load(['category', 'skills', 'images'])))->additional([
            'message' => 'Service récupéré avec succès.',
            'meta' => ['scope' => 'professional'],
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $this->authorize('update', $service);
        $service = $this->serviceManager->update($service, $request->validated());

        $this->auditLogService->record('service_updated', $service, $request->user(), [], $request);

        return (new ServiceResource($service))->additional([
            'message' => 'Service mis à jour avec succès.',
            'meta' => [],
        ]);
    }

    public function destroy(Request $request, Service $service): Response
    {
        $this->authorize('delete', $service);
        $this->serviceManager->archive($service);

        $this->auditLogService->record('service_archived', $service, $request->user(), [], $request);

        return response()->noContent();
    }

    public function publish(Request $request, Service $service): ServiceResource
    {
        $this->authorize('update', $service);
        $service = $this->serviceManager->publish($service);

        $this->auditLogService->record('service_published', $service, $request->user(), [], $request);

        return (new ServiceResource($service))->additional([
            'message' => 'Service publié avec succès.',
            'meta' => [],
        ]);
    }

    public function unpublish(Request $request, Service $service): ServiceResource
    {
        $this->authorize('update', $service);
        $service = $this->serviceManager->unpublish($service);

        $this->auditLogService->record('service_unpublished', $service, $request->user(), [], $request);

        return (new ServiceResource($service))->additional([
            'message' => 'Service dépublié avec succès.',
            'meta' => [],
        ]);
    }

    private function professionalProfile(Request $request): ProfessionalProfile
    {
        return $request->user()->professionalProfile()->firstOrFail();
    }
}
