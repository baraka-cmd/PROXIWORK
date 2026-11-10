<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PaymentRefundStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentRefund;
use App\Services\Audit\AuditLogService;
use App\Services\Payment\PaymentRefundAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(array_map(static fn (PaymentRefundStatus $s): string => $s->value, PaymentRefundStatus::cases()))],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);
        $filters['currency'] = isset($filters['currency']) ? strtoupper($filters['currency']) : null;

        $query = PaymentRefund::query()
            ->select(['id', 'reference', 'payment_id', 'client_id', 'amount', 'currency', 'status', 'reason', 'requested_at', 'processed_at'])
            ->with([
                'client:id,name,email',
                'payment:id,order_id,client_id,amount,currency,status,paid_at',
                'payment.order:id,order_number,professional_id',
                'payment.order.professional:id,user_id',
                'payment.order.professional.user:id,name',
            ])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('payment.order', fn ($order) => $order->where('order_number', 'like', "%{$search}%"))
                        ->orWhereHas('payment.order.professional.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['currency'] ?? null, fn ($q, string $currency) => $q->where('currency', $currency))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->where('requested_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->where('requested_at', '<', date('Y-m-d', strtotime($to.' +1 day')).' 00:00:00'))
            ->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'processing' THEN 1 ELSE 2 END")
            ->orderBy('requested_at')->orderBy('id');

        $statusCounts = PaymentRefund::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');

        $openTotals = PaymentRefund::query()
            ->whereIn('status', [PaymentRefundStatus::REQUESTED->value, PaymentRefundStatus::PROCESSING->value])
            ->selectRaw('currency, COUNT(*) as operation_count, SUM(amount) as amount_total')
            ->groupBy('currency')->get()->keyBy('currency');

        return view('admin.refunds.index', [
            'refunds' => $query->paginate($filters['per_page'] ?? 20)->withQueryString(),
            'filters' => $filters,
            'currencies' => PaymentRefund::query()->distinct()->orderBy('currency')->pluck('currency'),
            'statusCounts' => $statusCounts,
            'openTotals' => $openTotals,
        ]);
    }

    public function show(PaymentRefund $refund, Request $request, AuditLogService $audit): View
    {
        $refund = PaymentRefund::query()
            ->select(['id', 'reference', 'payment_id', 'client_id', 'amount', 'currency', 'status', 'reason', 'review_note', 'provider_reference', 'requested_at', 'processed_at', 'created_at'])
            ->with([
                'client:id,name,email',
                'payment:id,order_id,client_id,amount,currency,status,paid_at,provider',
                'payment.order:id,order_number,professional_id,status',
                'payment.order.professional:id,user_id',
                'payment.order.professional.user:id,name,email',
                'payment.transactions' => fn ($q) => $q->select(['id', 'payment_id', 'provider', 'status', 'provider_transaction_id', 'provider_reference', 'amount', 'currency', 'processed_at', 'created_at'])->where('status', 'succeeded')->latest('processed_at'),
                'payment.events' => fn ($q) => $q->select(['id', 'payment_id', 'payment_transaction_id', 'event_key', 'event_type', 'from_status', 'to_status', 'source', 'metadata', 'occurred_at'])
                    ->where('event_type', 'like', 'refund.%')->latest('occurred_at'),
            ])->findOrFail($refund->getKey());

        $audit->record('admin.refund.viewed', $refund, $request->user(), [
            'refund_id' => $refund->getKey(),
            'payment_id' => $refund->payment_id,
            'status' => $refund->status->value,
        ], $request);

        return view('admin.refunds.show', compact('refund'));
    }

    public function processing(Request $request, PaymentRefund $refund, PaymentRefundAdminService $service, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:2000']]);
        return $this->perform($request, $refund, $audit, 'admin.refund.processing', fn () => $service->startProcessing($refund, (int) $request->user()->id, $data['note']), $data['note'], 'La demande est prise en charge.');
    }

    public function reject(Request $request, PaymentRefund $refund, PaymentRefundAdminService $service, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:2000']]);
        return $this->perform($request, $refund, $audit, 'admin.refund.rejected', fn () => $service->reject($refund, (int) $request->user()->id, $data['note']), $data['note'], 'La demande a été refusée.');
    }

    public function succeeded(Request $request, PaymentRefund $refund, PaymentRefundAdminService $service, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider_reference' => ['required', 'string', 'max:191'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        return $this->perform($request, $refund, $audit, 'admin.refund.succeeded', fn () => $service->markSucceeded($refund, (int) $request->user()->id, $data['provider_reference'], $data['note']), $data['note'], 'Le résultat du remboursement a été enregistré avec sa référence externe.');
    }

    public function failed(Request $request, PaymentRefund $refund, PaymentRefundAdminService $service, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider_reference' => ['nullable', 'string', 'max:191'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        return $this->perform($request, $refund, $audit, 'admin.refund.failed', fn () => $service->markFailed($refund, (int) $request->user()->id, $data['note'], $data['provider_reference'] ?? null), $data['note'], 'L’échec du remboursement a été enregistré.');
    }

    private function perform(Request $request, PaymentRefund $refund, AuditLogService $audit, string $action, callable $operation, string $note, string $message): RedirectResponse
    {
        DB::transaction(function () use ($request, $refund, $audit, $action, $operation, $note): void {
            $updated = $operation();
            $audit->record($action, $updated, $request->user(), [
                'refund_id' => $updated->getKey(),
                'payment_id' => $updated->payment_id,
                'status' => $updated->status->value,
                'note_hash' => hash('sha256', trim($note)),
            ], $request);
        }, attempts: 3);

        return redirect()->route('admin.finance.refunds.show', $refund)->with('success', $message);
    }
}
