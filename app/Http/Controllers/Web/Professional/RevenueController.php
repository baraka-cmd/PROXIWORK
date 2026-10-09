<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\CommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->professionalProfile()->firstOrFail();

        $query = Commission::query()
            ->where('professional_id', $profile->getKey())
            ->with('order')
            ->latest('posted_at');

        if ($request->filled('currency')) {
            $query->where('currency', strtoupper((string) $request->string('currency')));
        }

        if ($request->filled('from')) {
            $query->whereDate('posted_at', '>=', Carbon::parse((string) $request->string('from')));
        }

        if ($request->filled('to')) {
            $query->whereDate('posted_at', '<=', Carbon::parse((string) $request->string('to')));
        }

        $commissions = (clone $query)->paginate(12)->withQueryString();

        $summary = (clone $query)
            ->where('status', CommissionStatus::POSTED)
            ->selectRaw('currency, COUNT(*) as orders_count, SUM(gross_amount) as gross_amount, SUM(commission_amount) as commission_amount, SUM(net_amount) as net_amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $currencies = Commission::query()
            ->where('professional_id', $profile->getKey())
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency');

        return view('professional.revenues.index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'currencies' => $currencies,
            'selectedCurrency' => $request->string('currency')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ]);
    }
}
