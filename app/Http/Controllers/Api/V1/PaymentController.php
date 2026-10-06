<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\Payment\PaymentIntentResource;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Order $order,
        PaymentService $paymentService,
    ): PaymentIntentResource|JsonResponse {
        $this->authorize('pay', $order);

        $intent = $paymentService->initiate(
            order: $order,
            client: $request->user(),
            method: $request->enum('payment_method', PaymentMethod::class),
            provider: $request->enum('payment_provider', PaymentProvider::class),
            idempotencyKey: $request->string('idempotency_key')->toString(),
        );

        $httpStatus = match ($intent->status) {
            PaymentStatus::SUCCEEDED => Response::HTTP_OK,
            PaymentStatus::PENDING,
            PaymentStatus::PROCESSING,
            PaymentStatus::INITIATED => Response::HTTP_ACCEPTED,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        return (new PaymentIntentResource($intent))
            ->additional([
                'message' => $intent->status === PaymentStatus::SUCCEEDED
                    ? 'Paiement accepté par le fournisseur.'
                    : 'Initiation de paiement enregistrée.',
            ])
            ->response()
            ->setStatusCode($httpStatus);
    }
}
