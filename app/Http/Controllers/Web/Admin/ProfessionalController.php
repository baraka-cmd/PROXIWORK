<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Professional\AdminProfessionalIndexRequest;
use App\Http\Requests\Admin\Verification\RejectProfessionalVerificationRequest;
use App\Http\Requests\Admin\Verification\StartProfessionalVerificationRequest;
use App\Http\Requests\Admin\Verification\VerifyProfessionalVerificationRequest;
use App\Models\ProfessionalProfile;
use App\Services\Admin\Professional\AdminProfessionalService;
use App\Services\Admin\Verification\ProfessionalVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfessionalController extends Controller
{
    public function __construct(
        private readonly AdminProfessionalService $professionalService,
        private readonly ProfessionalVerificationService $verificationService,
    ) {}

    public function index(AdminProfessionalIndexRequest $request): View
    {
        return view('admin.professionals.index', [
            'professionals' => $this->professionalService->paginate($request->validated()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(ProfessionalProfile $professional): View
    {
        $this->authorize('view', $professional);

        return view('admin.professionals.show', [
            'professional' => $this->professionalService->show($professional),
        ]);
    }

    public function startReview(
        StartProfessionalVerificationRequest $request,
        ProfessionalProfile $professional,
    ): RedirectResponse {
        $this->verificationService->startReview(
            $professional,
            $request->user(),
            $request,
            $request->validated('note'),
        );

        return back()->with('success', 'La vérification est maintenant en cours de revue.');
    }

    public function verify(
        VerifyProfessionalVerificationRequest $request,
        ProfessionalProfile $professional,
    ): RedirectResponse {
        $this->verificationService->verify(
            $professional,
            $request->user(),
            $request,
            $request->validated('note'),
        );

        return back()->with('success', 'Le profil professionnel a été vérifié.');
    }

    public function reject(
        RejectProfessionalVerificationRequest $request,
        ProfessionalProfile $professional,
    ): RedirectResponse {
        $this->verificationService->reject(
            $professional,
            $request->user(),
            $request,
            $request->validated('reason_code'),
            $request->validated('note'),
        );

        return back()->with('success', 'La vérification du profil a été rejetée.');
    }

    public function suspend(Request $request, ProfessionalProfile $professional): RedirectResponse
    {
        $this->authorize('suspend', $professional);
        $this->professionalService->suspend($professional, $request->user(), $request);

        return back()->with('success', 'Le compte professionnel a été suspendu.');
    }

    public function activate(Request $request, ProfessionalProfile $professional): RedirectResponse
    {
        $this->authorize('activate', $professional);
        $this->professionalService->activate($professional, $request->user(), $request);

        return back()->with('success', 'Le compte professionnel a été réactivé.');
    }
}
