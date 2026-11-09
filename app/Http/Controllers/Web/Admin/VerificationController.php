<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Professional\AdminProfessionalIndexRequest;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalProfile;
use App\Services\Admin\Professional\AdminProfessionalService;
use App\Services\Admin\Verification\ProfessionalVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(
        private readonly AdminProfessionalService $professionalService,
        private readonly ProfessionalVerificationService $verificationService,
    ) {}

    public function index(AdminProfessionalIndexRequest $request): View
    {
        $filters = $request->validated();

        $base = ProfessionalProfile::query();
        $metrics = [
            'pending' => (clone $base)->where('verification_status', 'pending')->count(),
            'under_review' => (clone $base)->where('verification_status', 'under_review')->count(),
            'additional_documents_requested' => (clone $base)->where('verification_status', 'additional_documents_requested')->count(),
            'verified' => (clone $base)->where('verification_status', 'verified')->count(),
            'rejected' => (clone $base)->where('verification_status', 'rejected')->count(),
            'documents_pending' => ProfessionalDocument::query()->where('status', ProfessionalDocument::STATUS_PENDING)->count(),
        ];

        $professionals = $this->professionalService->paginate($filters);

        return view('admin.verification.index', [
            'professionals' => $professionals,
            'filters' => $filters,
            'metrics' => $metrics,
        ]);
    }

    public function show(ProfessionalProfile $professional): View
    {
        $this->authorize('view', $professional);

        $professional = $this->professionalService->show($professional);

        return view('admin.verification.show', [
            'professional' => $professional,
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

    public function requestDocuments(Request $request, ProfessionalProfile $professional): RedirectResponse
    {
        $this->authorize('requestDocuments', $professional);
        $validated = $request->validate([
            'instructions' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $this->verificationService->requestAdditionalDocuments(
            $professional,
            $request->user(),
            $request,
            trim($validated['instructions']),
        );

        return back()->with('success', 'La demande de documents complémentaires a été envoyée.');
    }

    public function approveDocument(Request $request, ProfessionalProfile $professional, ProfessionalDocument $document): RedirectResponse
    {
        $this->authorize('reviewDocument', $professional);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->verificationService->reviewDocument(
            $professional,
            $document,
            $request->user(),
            $request,
            ProfessionalDocument::STATUS_APPROVED,
            $validated['note'] ?? null,
        );

        return back()->with('success', 'Le justificatif a été approuvé.');
    }

    public function rejectDocument(Request $request, ProfessionalProfile $professional, ProfessionalDocument $document): RedirectResponse
    {
        $this->authorize('reviewDocument', $professional);
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->verificationService->reviewDocument(
            $professional,
            $document,
            $request->user(),
            $request,
            ProfessionalDocument::STATUS_REJECTED,
            trim($validated['note']),
        );

        return back()->with('success', 'Le justificatif a été rejeté. Le professionnel devra transmettre une version corrigée.');
    }
}
