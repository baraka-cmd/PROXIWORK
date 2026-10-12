<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\PaymentConflictException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\IndexClientPaymentsRequest;
use App\Http\Requests\Payment\StoreClientRefundRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payment\PaymentReceiptService;
use App\Services\Payment\PaymentRefundRequestService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentReceiptService $receipts,
        private readonly PaymentRefundRequestService $refundRequests,
    ) {
    }

    public function index(IndexClientPaymentsRequest $request): View
    {
        $clientId = $request->user()->getKey();
        $filters = $request->validated();
        $ownedPayments = Payment::query()->where('client_id', $clientId);

        // Financial totals remain grouped by currency. USD and CDF are never added together.
        $dueByCurrency = Order::query()
            ->where('client_id', $clientId)
            ->where('status', OrderStatus::PENDING_PAYMENT->value)
            ->selectRaw('currency, SUM(total) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $confirmedByCurrency = (clone $ownedPayments)
            ->where('status', PaymentStatus::SUCCEEDED->value)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $processingByCurrency = (clone $ownedPayments)
            ->whereIn('status', [
                PaymentStatus::INITIATED->value,
                PaymentStatus::PENDING->value,
                PaymentStatus::PROCESSING->value,
            ])
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $refundRequestsByCurrency = PaymentRefund::query()
            ->where('client_id', $clientId)
            ->whereIn('status', [
                PaymentRefundStatus::REQUESTED->value,
                PaymentRefundStatus::PROCESSING->value,
            ])
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $currencies = (clone $ownedPayments)->distinct()->orderBy('currency')->pluck('currency');

        $query = (clone $ownedPayments)->with([
            'order.items',
            'transactions',
            'receipt',
            'refunds',
        ]);

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['method'] ?? null)) {
            $query->where('method', $filters['method']);
        }

        if (filled($filters['provider'] ?? null)) {
            $query->where('provider', $filters['provider']);
        }

        if (filled($filters['currency'] ?? null)) {
            $query->where('currency', $filters['currency']);
        }

        if (filled($filters['created_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (filled($filters['created_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (filled($filters['confirmed_from'] ?? null)) {
            $query->whereDate('paid_at', '>=', $filters['confirmed_from']);
        }

        if (filled($filters['confirmed_to'] ?? null)) {
            $query->whereDate('paid_at', '<=', $filters['confirmed_to']);
        }

        if (filled($filters['transaction_status'] ?? null)) {
            $query->whereHas('transactions', fn ($transactions) => $transactions
                ->where('status', $filters['transaction_status']));
        }

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $paymentIdTerm = preg_replace('/^PAY-/i', '', $term);
            $paymentId = preg_match('/^\d+$/', (string) $paymentIdTerm) === 1
                ? (int) $paymentIdTerm
                : null;

            $query->where(function ($search) use ($term, $paymentId): void {
                $search->whereHas('order', function ($orderQuery) use ($term): void {
                    $orderQuery->where('order_number', 'like', '%'.$term.'%')
                        ->orWhereHas('items', fn ($items) => $items
                            ->where('service_title', 'like', '%'.$term.'%'));
                });

                if ($paymentId !== null && $paymentId > 0) {
                    $search->orWhere('payments.id', $paymentId);
                }
            });
        }

        $payments = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $statusLabels = [
            PaymentStatus::INITIATED->value => 'Initialisé',
            PaymentStatus::PENDING->value => 'En attente',
            PaymentStatus::PROCESSING->value => 'En traitement / vérification',
            PaymentStatus::SUCCEEDED->value => 'Réussite simulée',
            PaymentStatus::FAILED->value => 'Échec simulé',
            PaymentStatus::CANCELLED->value => 'Annulé',
            PaymentStatus::EXPIRED->value => 'Expiré',
            PaymentStatus::REFUNDED->value => 'Remboursé',
        ];

        $methodLabels = [
            PaymentMethod::MOBILE_MONEY->value => 'Mobile Money simulé',
            PaymentMethod::CARD->value => 'Carte de test',
            PaymentMethod::BANK_TRANSFER->value => 'Virement de démonstration',
        ];

        $transactionStatusLabels = [
            PaymentTransactionStatus::INITIATED->value => 'Tentative créée',
            PaymentTransactionStatus::PENDING->value => 'En attente',
            PaymentTransactionStatus::PROCESSING->value => 'En traitement',
            PaymentTransactionStatus::UNKNOWN->value => 'Résultat inconnu — vérification nécessaire',
            PaymentTransactionStatus::SUCCEEDED->value => 'Réussite simulée',
            PaymentTransactionStatus::FAILED->value => 'Échec simulé',
            PaymentTransactionStatus::CANCELLED->value => 'Annulée',
            PaymentTransactionStatus::EXPIRED->value => 'Expirée',
            PaymentTransactionStatus::REFUNDED->value => 'Remboursée',
        ];

        return view('client.payments.index', compact(
            'payments',
            'filters',
            'dueByCurrency',
            'confirmedByCurrency',
            'processingByCurrency',
            'refundRequestsByCurrency',
            'statusLabels',
            'methodLabels',
            'transactionStatusLabels',
            'currencies',
        ));
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless($payment->client_id === $request->user()->getKey(), 404);

        $payment->load([
            'order.items',
            'order.professional.user',
            'transactions',
            'events',
            'refunds' => fn ($refunds) => $refunds->latest('created_at'),
        ]);

        $receipt = $this->receipts->findOrIssueFor($payment);
        $payment->setRelation('receipt', $receipt);

        return view('client.payments.show', compact('payment', 'receipt'));
    }

    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless($payment->client_id === $request->user()->getKey(), 404);

        $receipt = $this->receipts->findOrIssueFor($payment);
        abort_if($receipt === null, 404);
        abort_unless($this->receipts->verify($receipt), 409, 'Le reçu ne peut pas être vérifié à partir des données conservées.');

        return view('client.payments.receipt', compact('payment', 'receipt'));
    }

    public function requestRefund(
        StoreClientRefundRequest $request,
        Payment $payment,
    ): RedirectResponse {
        abort_unless($payment->client_id === $request->user()->getKey(), 404);

        try {
            $refund = $this->refundRequests->request(
                $payment,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (PaymentConflictException $exception) {
            return redirect()->route('client.payments.show', $payment)
                ->withErrors(['refund' => $exception->getMessage()]);
        }

        return redirect()->route('client.payments.show', $payment)
            ->with('success', 'Demande enregistrée sous la référence '.$refund->reference.'. Aucun remboursement n’a encore été exécuté : la demande doit être examinée.');
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('pay', $order);

        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ]);

        try {
            $payment = $this->payments->initiate(
                $order,
                $request->user(),
                PaymentMethod::from($data['method']),
                PaymentProvider::from($data['provider']),
                $data['idempotency_key'],
            );
        } catch (PaymentConflictException $exception) {
            return redirect()->route('client.orders.payment', $order)
                ->withErrors(['payment' => $exception->getMessage()]);
        } catch (PaymentGatewayUnavailableException) {
            if (app()->environment('production') || config('payment.simulation_enabled') !== true) {
                return redirect()->route('client.orders.payment', $order)
                    ->withErrors(['payment' => 'Le mode simulation est désactivé dans cet environnement. Aucun paiement réel n’a été effectué.']);
            }

            try {
                $payment = $this->payments->paymentForOrder($order, $request->user());

                return redirect()->route('client.payments.show', $payment)
                    ->withErrors(['payment' => 'Le résultat est incertain. Ne relancez pas un nouveau paiement : consultez cette tentative et vérifiez son état.']);
            } catch (PaymentConflictException) {
                return redirect()->route('client.orders.payment', $order)
                    ->withErrors(['payment' => 'Le simulateur est indisponible dans cet environnement. Aucun paiement réel n’a été effectué.']);
            }
        }

        $message = match ($payment->status) {
            PaymentStatus::SUCCEEDED => 'Réussite simulée enregistrée. Aucune transaction financière réelle n’a été effectuée.',
            PaymentStatus::FAILED => 'Échec simulé enregistré. La commande n’a pas été confirmée.',
            PaymentStatus::PROCESSING, PaymentStatus::PENDING, PaymentStatus::INITIATED => 'Tentative de simulation enregistrée. Son résultat n’est pas encore confirmé.',
            default => 'Résultat de simulation enregistré. Consultez le détail pour connaître l’état actuel.',
        };

        return redirect()->route('client.payments.show', $payment)->with('success', $message);
    }
}
