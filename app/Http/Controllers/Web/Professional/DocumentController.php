<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalDocument\StoreProfessionalDocumentRequest;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalProfile;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private readonly FileStorageService $storage,
    ) {}

    public function index(Request $request): View
    {
        $professional = $request->user()->professionalProfile()->firstOrFail();

        $latestDocumentsRequest = $professional->verificationReviews()
            ->where('to_status', 'additional_documents_requested')
            ->latest()
            ->first();

        return view('professional.documents.index', [
            'documents' => $professional->documents()->latest()->get(),
            'replaceableDocuments' => $professional->documents()->whereNull('superseded_by_document_id')->latest()->get(),
            'verificationStatus' => $professional->verification_status?->value,
            'verificationInstructions' => $latestDocumentsRequest?->note,
            'documentTypes' => [
                ProfessionalDocument::TYPE_IDENTITY => 'Pièce d’identité',
                ProfessionalDocument::TYPE_CERTIFICATE => 'Certificat ou diplôme',
                ProfessionalDocument::TYPE_TRAINING => 'Attestation de formation',
                ProfessionalDocument::TYPE_LICENSE => 'Licence ou autorisation',
                ProfessionalDocument::TYPE_REFERENCE => 'Référence professionnelle',
                ProfessionalDocument::TYPE_OTHER => 'Autre justificatif',
            ],
        ]);
    }

    public function store(StoreProfessionalDocumentRequest $request): RedirectResponse
    {
        $professional = $request->user()->professionalProfile()->firstOrFail();

        if (in_array($professional->verification_status?->value, ['verified', 'rejected'], true)) {
            return back()->withErrors([
                'document' => 'Votre dossier est clôturé dans son état actuel. Contactez le support PROXIWORK pour toute demande de réexamen.',
            ]);
        }

        $file = $request->file('file');
        $path = $this->storage->storeVerificationFile($file, $professional->getKey());

        try {
            DB::transaction(function () use ($professional, $request, $file, $path): void {
                $lockedProfile = ProfessionalProfile::query()->lockForUpdate()->findOrFail($professional->getKey());

                if (in_array($lockedProfile->verification_status?->value, ['verified', 'rejected'], true)) {
                    throw ValidationException::withMessages([
                        'document' => 'Le dossier est clôturé. Contactez le support PROXIWORK pour toute demande de réexamen.',
                    ]);
                }

                $newDocument = $lockedProfile->documents()->create([
                    'document_type' => $request->validated('document_type'),
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'disk' => 'local',
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'status' => ProfessionalDocument::STATUS_PENDING,
                ]);

                $replacedDocumentId = $request->validated('replaces_document_id');

                if ($replacedDocumentId !== null) {
                    $replacedDocument = ProfessionalDocument::query()
                        ->lockForUpdate()
                        ->findOrFail($replacedDocumentId);

                    if (
                        (int) $replacedDocument->professional_profile_id !== (int) $lockedProfile->getKey()
                        || $replacedDocument->document_type !== $newDocument->document_type
                        || $replacedDocument->superseded_by_document_id !== null
                    ) {
                        throw ValidationException::withMessages([
                            'replaces_document_id' => 'Choisissez un justificatif actuel de votre dossier appartenant à la même catégorie.',
                        ]);
                    }

                    $replacedDocument->forceFill([
                        'superseded_by_document_id' => $newDocument->getKey(),
                        'superseded_at' => now(),
                    ])->save();
                }
            });
        } catch (\Throwable $exception) {
            $this->storage->deletePrivate($path);
            throw $exception;
        }
        return back()->with('success', 'Le document a été transmis pour vérification.');
    }

    public function destroy(Request $request, ProfessionalDocument $document): RedirectResponse
    {
        $path = null;
        $disk = null;

        try {
            DB::transaction(function () use ($request, $document, &$path, &$disk): void {
                $professional = ProfessionalProfile::query()
                    ->lockForUpdate()
                    ->findOrFail($document->professional_profile_id);

                abort_unless($professional->user_id === $request->user()->getKey(), 403);

                if (in_array($professional->verification_status?->value, ['verified', 'rejected'], true)) {
                    throw ValidationException::withMessages([
                        'document' => 'Le dossier est clôturé ; les justificatifs ne peuvent plus être supprimés.',
                    ]);
                }

                $target = ProfessionalDocument::query()->lockForUpdate()->findOrFail($document->getKey());

                if ($target->superseded_by_document_id !== null || $target->status !== ProfessionalDocument::STATUS_PENDING || $target->reviews()->exists()) {
                    throw ValidationException::withMessages([
                        'document' => 'Un justificatif déjà examiné ne peut pas être supprimé. Transmettez une nouvelle version pour conserver la traçabilité.',
                    ]);
                }

                $path = $target->path;
                $disk = $target->disk;
                $target->delete();
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        if ($path !== null && $disk !== null) {
            Storage::disk($disk)->delete($path);
        }

        return back()->with('success', 'Le document a été supprimé.');
    }
}