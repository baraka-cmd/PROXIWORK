<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        $payments = Payment::query()->where('client_id', $request->user()->getKey())->with('order')->latest()->paginate(12)->withQueryString();

        return view('client.payments.index', compact('payments'));
    }

    public function show(Request $request, Payment $payment)
    {
        abort_unless($payment->client_id === $request->user()->getKey(), 403);

        $payment->load(['order', 'transactions']);
        return view('client.payments.show', compact('payment'));
    }

    public function store(Request $request, Order $order)
    {
        Gate::authorize('pay', $order);

        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ]);

        $payment = $this->payments->initiate(
            $order,
            $request->user(),
            PaymentMethod::from($data['method']),
            PaymentProvider::from($data['provider']),
            $data['idempotency_key'],
        );

        return redirect()->route('client.payments.show', $payment)
            ->with('success', 'La demande de paiement a été traitée.');
    }
}
