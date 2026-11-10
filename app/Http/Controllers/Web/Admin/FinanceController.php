<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\AdminFinanceFilterRequest;
use App\Models\Commission;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Services\Admin\Finance\AdminFinanceQueryService;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(
        private readonly AdminFinanceQueryService $finance,
    ) {}

    public function overview(AdminFinanceFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.finance.overview', [
            'overview' => $this->finance->overview($filters),
            'filters' => $filters,
        ]);
    }

    public function transactions(AdminFinanceFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.finance.transactions.index', [
            'transactions' => $this->finance->transactions($filters),
            'filters' => $filters,
        ]);
    }

    public function transaction(PaymentTransaction $transaction): View
    {
        return view('admin.finance.transactions.show', [
            'transaction' => $this->finance->transaction($transaction),
        ]);
    }

    public function commissions(AdminFinanceFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.finance.commissions.index', [
            'commissions' => $this->finance->commissions($filters),
            'filters' => $filters,
            'configuredRate' => config('commission.rate', '10.00'),
        ]);
    }

    public function commission(Commission $commission): View
    {
        return view('admin.finance.commissions.show', [
            'commission' => $this->finance->commission($commission),
            'configuredRate' => config('commission.rate', '10.00'),
        ]);
    }

    public function wallets(AdminFinanceFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.finance.wallets.index', [
            'wallets' => $this->finance->wallets($filters),
            'filters' => $filters,
        ]);
    }

    public function wallet(Wallet $wallet): View
    {
        return view('admin.finance.wallets.show', [
            'wallet' => $this->finance->wallet($wallet),
        ]);
    }
}
