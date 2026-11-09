<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalDocument\StoreProfessionalDocumentRequest;
use App\Models\ProfessionalDocument;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            $professional->documents()->create([
                'document_type' => $request->validated('document_type'),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => 'local',
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'status' => ProfessionalDocument::STATUS_PENDING,
            ]);
        } catch (\Throwable $exception) {
            $this->storage->deletePrivate($path);
            throw $exception;
        }

        return back()->with('success', 'Le document a été transmis pour vérification.');
    }

    public function destroy(Request $request, ProfessionalDocument $document): RedirectResponse
    {
        abort_unless($document->professionalProfile->user_id === $request->user()->getKey(), 403);

        if ($document->status !== ProfessionalDocument::STATUS_PENDING || $document->reviews()->exists()) {
            return back()->withErrors([
                'document' => 'Un justificatif déjà examiné ne peut pas être supprimé. Transmettez une nouvelle version pour conserver la traçabilité.',
            ]);
        }

        $path = $document->path;
        $document->delete();
        Storage::disk($document->disk)->delete($path);

        return back()->with('success', 'Le document a été supprimé.');
    }
}