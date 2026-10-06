<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Professional\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Professional\Dashboard\ProfessionalDashboardResource;
use App\Services\Professional\Dashboard\ProfessionalDashboardService;
use Illuminate\Http\Request;

class ProfessionalDashboardController extends Controller
{
    public function __construct(
        private readonly ProfessionalDashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): ProfessionalDashboardResource
    {
        $dashboard = $this->dashboardService->getFor($request->user());

        return (new ProfessionalDashboardResource($dashboard))->additional([
            'message' => 'Tableau de bord professionnel récupéré avec succès.',
            'meta' => [
                'scope' => 'professional',
            ],
        ]);
    }
}
