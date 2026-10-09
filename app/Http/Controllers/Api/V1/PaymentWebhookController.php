<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function handle(
        Request $request,
        string $provider,
        PaymentWebhookService $webhookService,
    ): JsonResponse {
        $paymentProvider = PaymentProvider::tryFrom($provider);

        if ($paymentProvider === null) {
            return response()->json(['message' => 'Fournisseur non supporté.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:191'],
            'transaction_id' => ['required', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'status' => ['required', 'string'],
            'failure_code' => ['nullable', 'string', 'max:64'],
            'failure_message' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $status = PaymentStatus::tryFrom($validated['status']);

        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Statut de paiement invalide.']);
        }

        try {
            $transaction = $webhookService->handle(
                provider: $paymentProvider,
                signature: (string) $request->header('X-Payment-Signature'),
                eventId: $validated['event_id'],
                transactionId: $validated['transaction_id'],
                amount: (string) $validated['amount'],
                currency: strtoupper($validated['currency']),
                status: $status,
                failureCode: $validated['failure_code'] ?? null,
                failureMessage: $validated['failure_message'] ?? null,
                metadata: $validated['metadata'] ?? [],
                rawBody: $request->getContent(),
            );
        } catch (Throwable $exception) {
            throw $exception;
        }

        return response()->json([
            'message' => 'Webhook traité.',
            'data' => [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status->value,
            ],
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
