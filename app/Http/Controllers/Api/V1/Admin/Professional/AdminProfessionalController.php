<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Professional\AdminProfessionalIndexRequest;
use App\Http\Resources\Admin\Professional\AdminProfessionalResource;
use App\Models\ProfessionalProfile;
use App\Services\Admin\Professional\AdminProfessionalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProfessionalController extends Controller
{
    public function __construct(
        private readonly AdminProfessionalService $service,
    ) {}

    public function index(AdminProfessionalIndexRequest $request): AnonymousResourceCollection
    {
        $professionals = $this->service->paginate($request->validated());

        return AdminProfessionalResource::collection($professionals)->additional([
            'message' => 'Professionnels récupérés avec succès.',
            'meta' => ['scope' => 'admin.professionals'],
        ]);
    }

    public function show(ProfessionalProfile $professional): AdminProfessionalResource
    {
        $this->authorize('view', $professional);

        return (new AdminProfessionalResource($this->service->show($professional)))->additional([
            'message' => 'Dossier professionnel récupéré avec succès.',
            'meta' => ['scope' => 'admin.professionals'],
        ]);
    }

    public function suspend(ProfessionalProfile $professional, Request $request): AdminProfessionalResource
    {
        $this->authorize('suspend', $professional);

        $updated = $this->service->suspend($professional, $request->user(), $request);

        return (new AdminProfessionalResource($updated))->additional([
            'message' => 'Professionnel suspendu avec succès.',
            'meta' => ['scope' => 'admin.professionals'],
        ]);
    }

    public function activate(ProfessionalProfile $professional, Request $request): AdminProfessionalResource
    {
        $this->authorize('activate', $professional);

        $updated = $this->service->activate($professional, $request->user(), $request);

        return (new AdminProfessionalResource($updated))->additional([
            'message' => 'Professionnel réactivé avec succès.',
            'meta' => ['scope' => 'admin.professionals'],
        ]);
    }
}
