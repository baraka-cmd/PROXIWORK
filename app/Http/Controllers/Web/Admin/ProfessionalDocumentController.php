<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalDocument;
use App\Services\Audit\AuditLogService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfessionalDocumentController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function download(Request $request, ProfessionalDocument $document): StreamedResponse
    {
        $professional = $document->professionalProfile;
        $this->authorize('viewVerificationDocuments', $professional);

        abort_unless(in_array($document->disk, ['local'], true), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        $this->auditLogService->record(
            'admin.professional.document.viewed',
            $professional,
            $request->user(),
            [
                'document_id' => $document->getKey(),
                'document_type' => $document->document_type,
            ],
            $request,
        );

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($document->original_name)) ?: 'justificatif';

        return $disk->download(
            $document->path,
            $safeName,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
