<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ProposeOrderScheduleRequest;
use App\Http\Requests\Order\ReportOrderBlockerRequest;
use App\Http\Requests\Order\SubmitOrderDeliveryRequest;
use App\Models\Order;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\Order\OrderWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowService $workflowService,
        private readonly AuditLogService $auditLogService,
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                ...array_map(
                    static fn (OrderStatus $status): string => $status->value,
                    OrderStatus::cases(),
                ),
                'active',
            ])],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        if (
            filled($filters['from'] ?? null)
            && filled($filters['to'] ?? null)
            && $filters['to'] < $filters['from']
        ) {
            throw ValidationException::withMessages([
                'to' => 'La date de fin doit être postérieure ou égale à la date de début.',
            ]);
        }

        $selectedStatus = (string) ($filters['status'] ?? '');
        $profile = $request->user()->professionalProfile()->firstOrFail();

        $query = Order::query()
            ->where('professional_id', $profile->getKey())
            ->with(['client:id,name,email', 'items', 'serviceRequest'])
            ->latest();

        if ($selectedStatus === 'active') {
            $query->whereIn('status', OrderStatus::operationallyActiveValues());
        } elseif ($selectedStatus !== '') {
            $status = OrderStatus::tryFrom($selectedStatus);

            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        // Completed orders are filtered by completion date; other views use creation date.
        $dateColumn = $selectedStatus === OrderStatus::COMPLETED->value ? 'completed_at' : 'created_at';
        if (filled($filters['from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }

        $orders = $query->paginate(12)->withQueryString();

        $counts = Order::query()
            ->where('professional_id', $profile->getKey())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statistics = [
            'pending_payment' => (int) ($counts[OrderStatus::PENDING_PAYMENT->value] ?? 0),
            'confirmed' => (int) ($counts[OrderStatus::CONFIRMED->value] ?? 0),
            'in_progress' => (int) ($counts[OrderStatus::IN_PROGRESS->value] ?? 0),
            'delivery_submitted' => (int) ($counts[OrderStatus::DELIVERY_SUBMITTED->value] ?? 0),
            'completed' => (int) ($counts[OrderStatus::COMPLETED->value] ?? 0),
            'disputed' => (int) ($counts[OrderStatus::DISPUTED->value] ?? 0),
        ];

        $statusLabels = [
            OrderStatus::PENDING_PAYMENT->value => 'En attente de paiement',
            OrderStatus::CONFIRMED->value => 'Confirmée / à planifier',
            OrderStatus::IN_PROGRESS->value => 'En cours',
            OrderStatus::DELIVERY_SUBMITTED->value => 'Livrable à vérifier',
            OrderStatus::COMPLETED->value => 'Terminée',
            OrderStatus::CANCELLED->value => 'Annulée',
            OrderStatus::DISPUTED->value => 'Contestée',
        ];

        return view('professional.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'statistics' => $statistics,
            'statusLabels' => $statusLabels,
            'selectedStatus' => $selectedStatus,
            'from' => (string) ($filters['from'] ?? ''),
            'to' => (string) ($filters['to'] ?? ''),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        abort_unless(
            $order->professional_id === $request->user()->professionalProfile?->getKey(),
            404
        );

        $order->load([
            'client:id,name,email',
            'items',
            'serviceRequest.service',
            'quotation',
            'acceptedOffer',
            'statusHistories',
            'deliveryAttachments',
            'addressSnapshot',
            'payment.transactions',
            'commission',
            'review',
        ]);

        return view('professional.orders.show', compact('order'));
    }

    public function proposeSchedule(ProposeOrderScheduleRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $validated = $request->validated();
        $timezone = config('app.timezone');

        $updated = $this->workflowService->proposeSchedule(
            $order,
            $request->user(),
            CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['starts_at'], $timezone),
            CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['ends_at'], $timezone),
            $validated['note'] ?? null,
        );

        $this->auditLogService->record('order_schedule_proposed', $updated, $request->user(), [
            'order_id' => $updated->getKey(),
        ], $request);
        $this->notificationService->accountActivity(
            $updated->client,
            'Planning proposé',
            'Le professionnel vous propose un planning pour votre commande. Vérifiez les dates et confirmez-les ou demandez une modification.',
            route('client.orders.show', $updated),
            'order-schedule-proposed:'.$updated->getKey().':'.$updated->statusHistories()->latest('id')->value('id'),
            'important',
        );

        return redirect()->route('professional.orders.show', $updated)
            ->with('success', 'La proposition de planning a été envoyée au client.');
    }

    public function startWork(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $updated = $this->workflowService->startWork($order, $request->user());

        $this->auditLogService->record('order_work_started', $updated, $request->user(), [
            'order_id' => $updated->getKey(),
        ], $request);
        $this->notificationService->accountActivity(
            $updated->client,
            'Prestation démarrée',
            'Le professionnel a démarré la réalisation de votre commande.',
            route('client.orders.show', $updated),
            'order-work-started:'.$updated->getKey(),
            'important',
        );

        return redirect()->route('professional.orders.show', $updated)
            ->with('success', 'Le démarrage de la prestation a été enregistré.');
    }

    public function submitDelivery(SubmitOrderDeliveryRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $updated = $this->workflowService->submitDelivery($order, $request->user(), $request->validated('summary'), $request->validated('attachments', []));

        $this->auditLogService->record('order_delivery_submitted', $updated, $request->user(), [
            'order_id' => $updated->getKey(),
        ], $request);
        $this->notificationService->accountActivity(
            $updated->client,
            'Livrable disponible',
            'Le professionnel a soumis le résultat de votre commande. Vérifiez-le avant de confirmer la réception.',
            route('client.orders.show', $updated),
            'order-delivery-submitted:'.$updated->getKey().':'.$updated->statusHistories()->latest('id')->value('id'),
            'important',
        );

        return redirect()->route('professional.orders.show', $updated)
            ->with('success', 'Le livrable a été soumis au client pour vérification.');
    }

    public function reportBlocker(ReportOrderBlockerRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $updated = $this->workflowService->reportBlocker($order, $request->user(), $request->validated('description'));

        $this->auditLogService->record('order_work_blocked', $updated, $request->user(), [
            'order_id' => $updated->getKey(),
        ], $request);
        $this->notificationService->accountActivity(
            $updated->client,
            'Mise à jour : difficulté signalée',
            'Le professionnel a signalé une difficulté concernant votre commande. Consultez son suivi.',
            route('client.orders.show', $updated),
            'order-work-blocked:'.$updated->getKey().':'.$updated->statusHistories()->latest('id')->value('id'),
            'important',
        );

        return redirect()->route('professional.orders.show', $updated)
            ->with('success', 'Le blocage a été enregistré et le client a été informé.');
    }
}
