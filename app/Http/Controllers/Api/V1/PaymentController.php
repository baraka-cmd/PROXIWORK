<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentConflictException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Order $order,
        PaymentService $paymentService,
    ): PaymentResource|JsonResponse {
        $this->authorize('pay', $order);

        try {
            $payment = $paymentService->initiate(
                order: $order,
                client: $request->user(),
                method: $request->enum('payment_method', PaymentMethod::class),
                provider: $request->enum('payment_provider', PaymentProvider::class),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (PaymentConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => [],
            ], Response::HTTP_CONFLICT);
        } catch (PaymentGatewayUnavailableException) {
            return response()->json([
                'message' => 'Le paiement n’a pas pu être confirmé auprès du fournisseur. Réutilisez la même clé d’idempotence pour reprendre la tentative.',
                'data' => null,
                'meta' => [],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($payment->status === PaymentStatus::SUCCEEDED) {
            $request->user()->notify(new \App\Notifications\AccountActivityNotification(
                'Paiement confirmé',
                'Votre paiement a été confirmé avec succès.',
                'payment',
            ));

            $payment->loadMissing('order.professional.user');
            $payment->order?->professional?->user?->notify(new \App\Notifications\AccountActivityNotification(
                'Commande confirmée',
                'Une commande vient d’être confirmée après paiement.',
                'order',
            ));
        }

        $httpStatus = match ($payment->status) {
            PaymentStatus::SUCCEEDED => Response::HTTP_OK,
            PaymentStatus::PENDING,
            PaymentStatus::PROCESSING,
            PaymentStatus::INITIATED => Response::HTTP_ACCEPTED,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        return (new PaymentResource($payment->load('transactions')))
            ->additional([
                'message' => $payment->status === PaymentStatus::SUCCEEDED
                    ? 'Paiement accepté par le fournisseur.'
                    : 'Initiation de paiement enregistrée.',
            ])
            ->response()
            ->setStatusCode($httpStatus);
    }

    public function show(
        Request $request,
        Order $order,
        PaymentService $paymentService,
    ): PaymentResource {
        $this->authorize('view', $order);

        return new PaymentResource(
            $paymentService->paymentForOrder($order, $request->user())
        );
    }
}
