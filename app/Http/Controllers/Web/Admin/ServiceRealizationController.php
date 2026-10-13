<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRealization;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
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
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function file(Request $request, ServiceRealization $realization): BinaryFileResponse
    {
        $this->authorize('adminView', $realization->service);

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

    public function approve(Request $request, ServiceRealization $realization): RedirectResponse
    {
        $this->authorize('adminManage', $realization->service);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $reviewed = $this->realizationManager->approve(
            $realization,
            $request->user(),
            $validated['review_note'] ?? null,
        );

        $this->auditLogService->record(
            'service_realization_approved',
            $reviewed,
            $request->user(),
            ['service_id' => $realization->service_id],
            $request,
        );

        $professional = $realization->service->professionalProfile->user;
        $this->notificationService->accountActivity(
            $professional,
            'Réalisation approuvée',
            'Une réalisation associée à votre service a été approuvée et pourra être affichée si le service est public.',
            route('professional.services.edit', $realization->service),
            'service-realization-review:'.$reviewed->getKey().':'.$reviewed->reviewed_at?->timestamp,
        );

        return back()->with('success', 'Réalisation approuvée. Elle pourra être affichée sur le service si celui-ci est publiquement disponible.');
    }

    public function reject(Request $request, ServiceRealization $realization): RedirectResponse
    {
        $this->authorize('adminManage', $realization->service);

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $reviewed = $this->realizationManager->reject(
            $realization,
            $request->user(),
            trim($validated['review_note']),
        );

        $this->auditLogService->record(
            'service_realization_rejected',
            $reviewed,
            $request->user(),
            ['service_id' => $realization->service_id],
            $request,
        );

        $professional = $realization->service->professionalProfile->user;
        $this->notificationService->accountActivity(
            $professional,
            'Réalisation à corriger',
            'Une réalisation associée à votre service a été refusée. Consultez le motif dans la gestion de votre service.',
            route('professional.services.edit', $realization->service),
            'service-realization-review:'.$reviewed->getKey().':'.$reviewed->reviewed_at?->timestamp,
        );

        return back()->with('success', 'Réalisation refusée. Le motif a été enregistré.');
    }
}
