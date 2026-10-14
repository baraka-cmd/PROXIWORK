<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Professional\Wallet\ProfessionalWalletFilterRequest;
use App\Models\ProfessionalProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(ProfessionalWalletFilterRequest $request): View
    {
        $profile = ProfessionalProfile::query()
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        $currencies = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->orderBy('currency')
            ->pluck('currency');

        $filters = $request->validated();
        $currency = $filters['currency'] ?? $currencies->first() ?? 'USD';

        if ($currencies->isNotEmpty() && ! $currencies->contains($currency)) {
            throw ValidationException::withMessages([
                'currency' => 'Aucun portefeuille ne correspond à la devise sélectionnée.',
            ]);
        }

        $wallet = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->where('currency', $currency)
            ->first();

        if ($wallet !== null) {
            $this->authorize('view', $wallet);
        }

        $transactions = null;

        if ($wallet !== null) {
            $query = WalletTransaction::query()
                ->where('wallet_id', $wallet->getKey())
                ->latest('created_at')
                ->latest('id');

            if (! empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (! empty($filters['direction'])) {
                $query->where('direction', $filters['direction']);
            }

            if (! empty($filters['transaction_id'])) {
                $query->whereKey($filters['transaction_id']);
            }

            if (! empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
            }

            if (! empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
            }

            $transactions = $query->paginate(15)->withQueryString();
        }

        return view('professional.wallet.index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'currencies' => $currencies,
            'currency' => $currency,
            'transactionTypes' => WalletTransactionType::cases(),
            'transactionDirections' => WalletTransactionDirection::cases(),
            'filters' => $filters,
        ]);
    }
}
