<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AdminAnalyticsIndexRequest;
use App\Services\Admin\Analytics\AdminAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsService $analyticsService,
    ) {}

    public function index(AdminAnalyticsIndexRequest $request): View
    {
        $from = CarbonImmutable::parse($request->validated('from'))->startOfDay();
        $to = CarbonImmutable::parse($request->validated('to'))->endOfDay();

        return view('admin.analytics.index', [
            'analytics' => $this->analyticsService->get($from, $to),
            'from' => $from,
            'to' => $to,
            'range' => $request->validated('range', '30d'),
        ]);
    }
}
