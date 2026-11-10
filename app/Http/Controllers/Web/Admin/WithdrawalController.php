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
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(array_map(
                static fn (WithdrawalStatus $status): string => $status->value,
                WithdrawalStatus::cases(),
            ))],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'provider' => ['nullable', 'string', Rule::in(['mobile_money', 'bank_transfer'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);
        $filters['currency'] = isset($filters['currency']) ? strtoupper($filters['currency']) : null;

        $query = Withdrawal::query()
            ->with(['professional.user', 'wallet'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search);
                    }
                    $nested->orWhereHas('professional.user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('provider_transaction_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('currency', $currency))
            ->when($filters['provider'] ?? null, fn ($query, string $provider) => $query->where('provider', $provider))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('requested_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('requested_at', '<', date('Y-m-d', strtotime($to.' +1 day')).' 00:00:00'))
            ->latest('requested_at')->latest('id');

        $statusCounts = Withdrawal::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');

        $reservedTotals = Withdrawal::query()
            ->whereIn('status', [
                WithdrawalStatus::REQUESTED->value,
                WithdrawalStatus::PROCESSING->value,
                WithdrawalStatus::REVIEW_REQUIRED->value,
            ])
            ->selectRaw('currency, COUNT(*) as operation_count, SUM(amount) as amount_total')
            ->groupBy('currency')->get()->keyBy('currency');

        return view('admin.withdrawals.index', [
            'withdrawals' => $query->paginate($filters['per_page'] ?? 20)->withQueryString(),
            'filters' => $filters,
            'currencies' => Withdrawal::query()->distinct()->orderBy('currency')->pluck('currency'),
            'statusCounts' => $statusCounts,
            'reservedTotals' => $reservedTotals,
        ]);
    }

    public function show(Request $request, Withdrawal $withdrawal, AuditLogService $auditLogs): View
    {
        $withdrawal->load(['professional.user', 'wallet', 'statusHistories.actor']);
        $canViewDestination = $request->user()->can('withdrawals.destination.view');

        $auditLogs->record(
            action: 'admin.withdrawal.viewed',
            subject: $withdrawal,
            user: $request->user(),
            metadata: ['withdrawal_id' => $withdrawal->getKey(), 'status' => $withdrawal->status->value],
            request: $request,
        );

        if ($canViewDestination) {
            $auditLogs->record(
                action: 'admin.withdrawal.destination.viewed',
                subject: $withdrawal,
                user: $request->user(),
                metadata: ['withdrawal_id' => $withdrawal->getKey()],
                request: $request,
            );
        }

        return view('admin.withdrawals.show', compact('withdrawal', 'canViewDestination'));
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
