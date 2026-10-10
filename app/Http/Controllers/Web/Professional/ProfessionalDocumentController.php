<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalDocument\StoreProfessionalDocumentRequest;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfessionalDocumentController extends Controller
{
    public function store(
        StoreProfessionalDocumentRequest $request,
        AuditLogService $auditLogService,
    ): RedirectResponse {
        $profile = $request->user()->professionalProfile()->firstOrFail();
        $file = $request->file('file');
        $path = $file->store('professional-documents', 'local');

        try {
            $document = $profile->documents()->create([
                'document_type' => $request->validated('type'),
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'review_status' => 'pending',
                'submitted_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $auditLogService->record(
            'professional.document.uploaded',
            $document,
            $request->user(),
            ['document_type' => $document->document_type],
            $request,
        );

        return back()->with('success', 'Le document a été ajouté à votre dossier privé.');
    }
}
