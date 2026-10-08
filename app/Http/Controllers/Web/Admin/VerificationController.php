<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Professional\AdminProfessionalIndexRequest;
use App\Models\ProfessionalProfile;
use App\Services\Admin\Professional\AdminProfessionalService;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(
        private readonly AdminProfessionalService $professionalService,
    ) {}

    public function index(AdminProfessionalIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.verification.index', [
            'professionals' => $this->professionalService->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function show(ProfessionalProfile $professional): View
    {
        $this->authorize('view', $professional);

        return view('admin.professionals.show', [
            'professional' => $this->professionalService->show($professional),
            'verificationCenter' => true,
        ]);
    }
}