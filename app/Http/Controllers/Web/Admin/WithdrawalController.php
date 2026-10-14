<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogService;
use App\Services\Wallet\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_map(
                static fn (WithdrawalStatus $status): string => $status->value,
                WithdrawalStatus::cases(),
            ))],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ]);
        $filters['currency'] = isset($filters['currency']) ? strtoupper($filters['currency']) : null;

        $query = Withdrawal::query()
            ->with(['professional.user', 'wallet'])
            ->latest('requested_at')
            ->latest('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        return view('admin.withdrawals.index', [
            'withdrawals' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'currencies' => Withdrawal::query()->distinct()->orderBy('currency')->pluck('currency'),
        ]);
    }

    public function show(Request $request, Withdrawal $withdrawal, AuditLogService $auditLogs): View
    {
        $withdrawal->load(['professional.user', 'wallet', 'statusHistories.actor']);

        // Only the dedicated financial permission can reach this screen. Log the
        // access without copying the sensitive destination into audit metadata.
        $auditLogs->record(
            action: 'admin.withdrawal.destination.viewed',
            subject: $withdrawal,
            user: $request->user(),
            metadata: ['withdrawal_id' => $withdrawal->getKey(), 'status' => $withdrawal->status->value],
            request: $request,
        );

        return view('admin.withdrawals.show', compact('withdrawal'));
    }

    public function startProcessing(
        Request $request,
        Withdrawal $withdrawal,
        WithdrawalService $service,
        AuditLogService $auditLogs,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $withdrawal, $service, $auditLogs, $data): void {
            $updated = $service->markProcessing($withdrawal, (int) $request->user()->getKey(), $data['reason']);
            $auditLogs->record(
                'admin.withdrawal.processing_started',
                $updated,
                $request->user(),
                ['withdrawal_id' => $updated->getKey(), 'reason' => $data['reason']],
                $request,
            );
        }, attempts: 3);

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'Le traitement a été démarré. Aucun fournisseur externe n’a été appelé.');
    }

    public function reject(
        Request $request,
        Withdrawal $withdrawal,
        WithdrawalService $service,
        AuditLogService $auditLogs,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $withdrawal, $service, $auditLogs, $data): void {
            $updated = $service->reject(
                $withdrawal,
                'ADMIN_REJECTED',
                $data['reason'],
                (int) $request->user()->getKey(),
            );
            $auditLogs->record(
                'admin.withdrawal.rejected',
                $updated,
                $request->user(),
                ['withdrawal_id' => $updated->getKey(), 'reason' => $data['reason']],
                $request,
            );
        }, attempts: 3);

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'La demande a été refusée et la réservation a été libérée.');
    }

    public function markSucceeded(
        Request $request,
        Withdrawal $withdrawal,
        WithdrawalService $service,
        AuditLogService $auditLogs,
    ): RedirectResponse {
        $data = $request->validate([
            'provider_transaction_id' => ['required', 'string', 'max:191'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $withdrawal, $service, $auditLogs, $data): void {
            $updated = $service->markSucceeded(
                $withdrawal,
                $data['provider_transaction_id'],
                (int) $request->user()->getKey(),
                $data['reason'],
            );
            $auditLogs->record(
                'admin.withdrawal.succeeded',
                $updated,
                $request->user(),
                [
                    'withdrawal_id' => $updated->getKey(),
                    'provider_transaction_id' => $data['provider_transaction_id'],
                    'reason' => $data['reason'],
                ],
                $request,
            );
        }, attempts: 3);

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'Le retrait a été confirmé et son mouvement financier a été enregistré.');
    }

    public function markFailed(
        Request $request,
        Withdrawal $withdrawal,
        WithdrawalService $service,
        AuditLogService $auditLogs,
    ): RedirectResponse {
        $data = $request->validate([
            'failure_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/'],
            'failure_message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $withdrawal, $service, $auditLogs, $data): void {
            $updated = $service->markFailed(
                $withdrawal,
                $data['failure_code'],
                $data['failure_message'],
                (int) $request->user()->getKey(),
                $data['failure_message'],
            );
            $auditLogs->record(
                'admin.withdrawal.failed',
                $updated,
                $request->user(),
                [
                    'withdrawal_id' => $updated->getKey(),
                    'failure_code' => $data['failure_code'],
                    'reason' => $data['failure_message'],
                ],
                $request,
            );
        }, attempts: 3);

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'L’échec confirmé a été enregistré et les fonds ont été libérés.');
    }

    public function markReviewRequired(
        Request $request,
        Withdrawal $withdrawal,
        WithdrawalService $service,
        AuditLogService $auditLogs,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $withdrawal, $service, $auditLogs, $data): void {
            $updated = $service->markReviewRequired(
                $withdrawal,
                $data['reason'],
                (int) $request->user()->getKey(),
            );
            $auditLogs->record(
                'admin.withdrawal.review_required',
                $updated,
                $request->user(),
                ['withdrawal_id' => $updated->getKey(), 'reason' => $data['reason']],
                $request,
            );
        }, attempts: 3);

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'Le retrait est placé en vérification. Les fonds restent bloqués.');
    }
}
