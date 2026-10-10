<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalDocument;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfessionalDocumentController extends Controller
{
    public function download(
        Request $request,
        ProfessionalDocument $document,
        AuditLogService $auditLogService,
    ): StreamedResponse {
        abort_unless(
            $request->user()?->hasPermissionTo('admin.professionals.view'),
            403,
        );

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $auditLogService->record(
            'admin.professional_document.downloaded',
            $document,
            $request->user(),
            [
                'document_type' => $document->document_type,
                'professional_profile_id' => $document->professional_profile_id,
            ],
            $request,
        );

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
