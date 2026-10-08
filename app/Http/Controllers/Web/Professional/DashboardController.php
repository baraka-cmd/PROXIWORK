<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Services\Professional\Dashboard\ProfessionalDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProfessionalDashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): View
    {
        $dashboard = $this->dashboardService->getFor($request->user());

        return view('professional.dashboard', compact('dashboard'));
    }
}
