<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWithdrawalRequest;
use App\Http\Resources\Wallet\WalletResource;
use App\Http\Resources\Wallet\WithdrawalResource;
use App\Models\ProfessionalProfile;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $walletService): WalletResource
    {
        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->getKey())->firstOrFail();
        $currency = strtoupper((string) $request->query('currency', 'USD'));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency' => 'Devise invalide.']);
        }

        return new WalletResource($walletService->getOrCreate($profile, $currency));
    }

    public function withdraw(
        StoreWithdrawalRequest $request,
        WithdrawalService $withdrawalService,
    ): WithdrawalResource {
        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->getKey())->firstOrFail();

        $withdrawal = $withdrawalService->request(
            professional: $profile,
            amount: $request->string('amount')->toString(),
            currency: $request->string('currency')->toString(),
            provider: $request->string('provider')->toString(),
            destination: $request->string('destination')->toString(),
            idempotencyKey: $request->string('idempotency_key')->toString(),
        );

        return (new WithdrawalResource($withdrawal))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
