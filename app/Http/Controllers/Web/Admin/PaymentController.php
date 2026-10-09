<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\AdminPaymentIndexRequest;
use App\Models\Payment;
use App\Services\Admin\Payment\AdminPaymentManagementService;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly AdminPaymentManagementService $paymentManager,
    ) {}

    public function index(AdminPaymentIndexRequest $request): View
    {
        return view('admin.payments.index', [
            'payments' => $this->paymentManager->paginate($request->validated()),
            'filters' => $request->validated(),
            'providers' => array_map(
                fn (PaymentProvider $provider): string => $provider->value,
                PaymentProvider::cases(),
            ),
        ]);
    }

    public function show(Payment $payment): View
    {
        return view('admin.payments.show', [
            'payment' => $this->paymentManager->show($payment),
        ]);
    }
}
