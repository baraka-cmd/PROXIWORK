<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\TechnicalMonitoringService;
use Illuminate\View\View;

class TechnicalMonitoringController extends Controller
{
    public function index(TechnicalMonitoringService $monitoring): View
    {
        return view('admin.monitoring.index', [
            'monitoring' => $monitoring->dashboard(),
        ]);
    }
}
