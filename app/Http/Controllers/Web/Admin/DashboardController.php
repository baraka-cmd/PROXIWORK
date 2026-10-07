<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\AdminDashboardRequest;
use App\Services\Admin\Dashboard\AdminDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboardService) {}

    public function index(AdminDashboardRequest $request): View
    {
        $dashboard = $this->dashboardService->get(
            $request->date('from'),
            $request->date('to'),
        );

        return view('admin.dashboard', [
            'dashboard' => $dashboard,
            'from' => $request->date('from'),
            'to' => $request->date('to'),
        ]);
    }
}
