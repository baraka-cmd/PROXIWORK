<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\Dashboard\ClientDashboardResource;
use App\Services\Client\Dashboard\ClientDashboardService;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function __construct(
        private readonly ClientDashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): ClientDashboardResource
    {
        $dashboard = $this->dashboardService->getFor($request->user());

        return (new ClientDashboardResource($dashboard))->additional([
            'message' => 'Tableau de bord client récupéré avec succès.',
            'meta' => [
                'scope' => 'client',
            ],
        ]);
    }
}
