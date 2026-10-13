<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\StoreServiceRealizationRequest;
use App\Http\Resources\ProfessionalService\ServiceRealizationResource;
use App\Models\Service;
use App\Models\ServiceRealization;
use App\Services\Audit\AuditLogService;
use App\Services\ProfessionalService\ServiceRealizationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalServiceRealizationController extends Controller
{
    public function __construct(
        private readonly ServiceRealizationManager $realizationManager,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request, Service $service): AnonymousResourceCollection
    {
        $this->authorize('view', $service);

        return ServiceRealizationResource::collection(
            $service->realizations()->paginate(15)->withQueryString(),
        )->additional([
            'message' => 'Réalisations du service récupérées avec succès.',
            'meta' => ['scope' => 'professional'],
        ]);
    }

    public function store(StoreServiceRealizationRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $realization = $this->realizationManager->submit(
            $service,
            $request->file('image'),
            $request->validated(),
        );

        $this->auditLogService->record(
            'service_realization_submitted',
            $realization,
            $request->user(),
            ['service_id' => $service->getKey()],
            $request,
        );

        return (new ServiceRealizationResource($realization))->additional([
            'message' => 'Réalisation soumise à la modération. Elle reste privée jusqu’à son approbation.',
            'meta' => [],
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function file(ServiceRealization $realization): BinaryFileResponse
    {
        $this->authorize('view', $realization->service);

        abort_unless(Storage::disk('local')->exists($realization->path), 404);

        $mimeType = Storage::disk('local')->mimeType($realization->path);
        abort_unless(
            is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true),
            404,
        );

        $response = response()->file(Storage::disk('local')->path($realization->path));
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function destroy(Request $request, ServiceRealization $realization): Response
    {
        $this->authorize('update', $realization->service);

        $this->realizationManager->delete($realization, $request->user());

        $this->auditLogService->record(
            'service_realization_deleted',
            $realization,
            $request->user(),
            ['service_id' => $realization->service_id],
            $request,
        );

        return response()->noContent();
    }
}
