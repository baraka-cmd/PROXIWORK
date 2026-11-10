<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\Admin\Finance\AdminFinancialOperationsQueryService;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialOperationsController extends Controller
{
    public function movements(Request $request, AdminFinancialOperationsQueryService $queries): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'type' => ['nullable', 'string', Rule::in(['earning', 'commission', 'withdrawal', 'refund', 'adjustment', 'reversal'])],
            'direction' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'bucket' => ['nullable', 'string', Rule::in(['available', 'pending', 'locked'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);
        $filters['currency'] = isset($filters['currency']) ? strtoupper($filters['currency']) : null;

        return view('admin.finance.movements.index', [
            'movements' => $queries->movements($filters),
            'filters' => $filters,
            'currencies' => WalletTransaction::query()->distinct()->orderBy('currency')->pluck('currency'),
        ]);
    }

    public function movement(WalletTransaction $movement, Request $request, AdminFinancialOperationsQueryService $queries, AuditLogService $audit): View
    {
        $movement = $queries->movement($movement);
        $audit->record('admin.finance.movement.viewed', $movement, $request->user(), [
            'wallet_transaction_id' => $movement->getKey(),
            'wallet_id' => $movement->wallet_id,
            'type' => $movement->type->value,
            'currency' => $movement->currency,
        ], $request);

        return view('admin.finance.movements.show', compact('movement'));
    }

    public function reports(Request $request, AdminFinancialOperationsQueryService $queries, AuditLogService $audit): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'professional_id' => ['nullable', 'integer', 'exists:professional_profiles,id'],
        ]);
        $filters['currency'] = isset($filters['currency']) ? strtoupper($filters['currency']) : null;
        $audit->record('admin.finance.report.viewed', null, $request->user(), $filters, $request);

        return view('admin.finance.reports.index', [
            'report' => $queries->reports($filters),
            'filters' => $filters,
            'currencies' => Payment::query()->distinct()->orderBy('currency')->pluck('currency')->merge(WalletTransaction::query()->distinct()->orderBy('currency')->pluck('currency'))->unique()->sort()->values(),
        ]);
    }

    public function export(Request $request, AdminFinancialOperationsQueryService $queries, AuditLogService $audit): StreamedResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'professional_id' => ['nullable', 'integer', 'exists:professional_profiles,id'],
        ]);
        $filters['currency'] = isset($filters['currency']) ? strtoupper($filters['currency']) : null;

        $audit->record('admin.finance.report.exported', null, $request->user(), [
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'professional_id' => $filters['professional_id'] ?? null,
        ], $request);

        return response()->streamDownload(function () use ($queries, $filters): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Date', 'Type', 'Référence', 'Client / professionnel', 'Montant', 'Devise', 'Statut', 'Référence source'], ';', '"', '');
            foreach ($queries->exportRows($filters) as $row) {
                fputcsv($output, array_map([$this, 'safeCsv'], $row), ';', '"', '');
            }
            fclose($output);
        }, 'proxiwork-rapport-financier-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
