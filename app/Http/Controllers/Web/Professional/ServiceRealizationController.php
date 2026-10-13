<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\StoreServiceRealizationRequest;
use App\Models\Service;
use App\Models\ServiceRealization;
use App\Services\Audit\AuditLogService;
use App\Services\ProfessionalService\ServiceRealizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServiceRealizationController extends Controller
{
    public function __construct(
        private readonly ServiceRealizationManager $realizationManager,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function store(StoreServiceRealizationRequest $request, Service $service): RedirectResponse
    {
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

        return back()->with('success', 'Réalisation envoyée à la modération. Elle ne sera visible des clients qu’après approbation.');
    }

    public function file(ServiceRealization $realization): BinaryFileResponse
    {
        $this->authorize('view', $realization->service);

        $path = $realization->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $mimeType = Storage::disk('local')->mimeType($path);
        abort_unless(
            is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true),
            404,
        );

        $response = response()->file(Storage::disk('local')->path($path));
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function destroy(Request $request, ServiceRealization $realization): RedirectResponse
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

        return back()->with('success', 'Réalisation supprimée.');
    }
}
