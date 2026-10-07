<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\AdminDashboardRequest;
use App\Http\Resources\Admin\Dashboard\AdminDashboardResource;
use App\Services\Admin\Dashboard\AdminDashboardService;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboardService) {}

    public function __invoke(AdminDashboardRequest $request): AdminDashboardResource
    {
        $dashboard = $this->dashboardService->get($request->date('from'), $request->date('to'));

        return (new AdminDashboardResource($dashboard))->additional([
            'message' => 'Tableau de bord administrateur récupéré avec succès.',
            'meta' => ['scope' => 'admin'],
        ]);
    }
}
