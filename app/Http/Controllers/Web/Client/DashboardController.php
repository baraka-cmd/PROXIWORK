<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\Dashboard\ClientDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ClientDashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('client.dashboard', [
            'dashboard' => $this->dashboardService->getFor($request->user()),
        ]);
    }
}
