<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\StoreServiceRequestAttachmentRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServiceRequestAttachmentController extends Controller
{
    public function store(
        StoreServiceRequestAttachmentRequest $request,
        ServiceRequest $serviceRequest,
        AuditLogService $auditLogService,
        TransactionalNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorize('view', $serviceRequest);
        abort_unless($serviceRequest->client_id === $request->user()->getKey(), 403);

        $file = $request->file('attachment');
        $storedPath = null;

        try {
            $attachment = DB::transaction(function () use ($serviceRequest, $request, $file, &$storedPath): ServiceRequestAttachment {
                $lockedRequest = ServiceRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($serviceRequest->getKey());

                if ($lockedRequest->client_id !== $request->user()->getKey()) {
                    abort(403);
                }

                if (! in_array($lockedRequest->status, [ServiceRequestStatus::DRAFT, ServiceRequestStatus::REQUESTED], true)) {
                    throw ValidationException::withMessages([
                        'attachment' => 'Les pièces jointes ne peuvent plus être ajoutées après le début de l’évaluation du devis.',
                    ]);
                }

                if ($lockedRequest->attachments()->count() >= 5) {
                    throw ValidationException::withMessages([
                        'attachment' => 'Une demande peut contenir au maximum cinq pièces jointes.',
                    ]);
                }

                $storedPath = $file->store('service-request-attachments/'.$lockedRequest->getKey(), 'local');
                if (! is_string($storedPath) || $storedPath === '') {
                    throw ValidationException::withMessages([
                        'attachment' => 'Le fichier n’a pas pu être enregistré. Réessayez.',
                    ]);
                }

                $originalName = basename((string) $file->getClientOriginalName());
                $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? 'document';
                $originalName = mb_substr(trim($originalName) ?: 'document', 0, 255);

                return $lockedRequest->attachments()->create([
                    'uploaded_by' => $request->user()->getKey(),
                    'path' => $storedPath,
                    'original_name' => $originalName,
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        $serviceRequest->loadMissing('professional.user');
        $auditLogService->record('service_request_attachment_uploaded', $attachment, $request->user(), [
            'service_request_id' => $serviceRequest->getKey(),
            'attachment_id' => $attachment->getKey(),
            'size' => $attachment->size,
            'sha256' => $attachment->sha256,
        ], $request);

        if ($serviceRequest->status === ServiceRequestStatus::REQUESTED && $serviceRequest->professional?->user !== null) {
            $notificationService->accountActivity(
                $serviceRequest->professional->user,
                'Pièce jointe ajoutée à une demande',
                'Le client a ajouté un document à une demande que vous évaluez.',
                route('professional.requests.show', $serviceRequest),
                'service-request-attachment:'.$attachment->getKey(),
                'important',
            );
        }

        return redirect()
            ->route('client.requests.show', $serviceRequest)
            ->with('success', 'La pièce jointe privée a été ajoutée à votre demande.');
    }

    public function download(
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment,
    ): StreamedResponse {
        $this->authorize('view', $serviceRequest);
        abort_unless($attachment->service_request_id === $serviceRequest->getKey(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
