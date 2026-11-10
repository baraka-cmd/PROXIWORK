<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\AdminFinanceFilterRequest;
use App\Models\Commission;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Services\Admin\Finance\AdminFinanceQueryService;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
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

    public function transaction(PaymentTransaction $transaction, Request $request, AuditLogService $auditLogs): View
    {
        $transaction = $this->finance->transaction($transaction);

        $auditLogs->record(
            action: 'admin.finance.transaction.viewed',
            subject: $transaction,
            user: $request->user(),
            metadata: ['payment_transaction_id' => $transaction->getKey(), 'payment_id' => $transaction->payment_id, 'status' => $transaction->status->value],
            request: $request,
        );

        return view('admin.finance.transactions.show', compact('transaction'));
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

    public function commission(Commission $commission, Request $request, AuditLogService $auditLogs): View
    {
        $commission = $this->finance->commission($commission);

        $auditLogs->record(
            action: 'admin.finance.commission.viewed',
            subject: $commission,
            user: $request->user(),
            metadata: ['commission_id' => $commission->getKey(), 'status' => $commission->status->value, 'currency' => $commission->currency],
            request: $request,
        );

        return view('admin.finance.commissions.show', [
            'commission' => $commission,
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

    public function wallet(Wallet $wallet, Request $request, AuditLogService $auditLogs): View
    {
        $wallet = $this->finance->wallet($wallet);

        $auditLogs->record(
            action: 'admin.finance.wallet.viewed',
            subject: $wallet,
            user: $request->user(),
            metadata: ['wallet_id' => $wallet->getKey(), 'currency' => $wallet->currency, 'status' => $wallet->status],
            request: $request,
        );

        return view('admin.finance.wallets.show', compact('wallet'));
    }
}
