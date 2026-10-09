<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function index(Request $request, WalletService $walletService)
    {
        $profile = $this->professionalProfile($request);
        $currency = strtoupper((string) $request->query('currency', 'USD'));
        $this->validateCurrency($currency);

        $wallet = $walletService->getOrCreate($profile, $currency);
        $this->authorize('view', $wallet);

        $transactions = WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $currencies = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->orderBy('currency')
            ->pluck('currency');

        if ($currencies->isEmpty()) {
            $currencies = collect([$currency]);
        }

        return view('professional.wallet.index', compact('wallet', 'transactions', 'currencies'));
    }

    private function professionalProfile(Request $request): ProfessionalProfile
    {
        return ProfessionalProfile::query()
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }

    private function validateCurrency(string $currency): void
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages([
                'currency' => 'La devise sélectionnée est invalide.',
            ]);
        }
    }
}
