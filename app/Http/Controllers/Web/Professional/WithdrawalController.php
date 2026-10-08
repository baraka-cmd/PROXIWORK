<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web\Professional;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWithdrawalRequest;
use App\Models\ProfessionalProfile;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
class WithdrawalController extends Controller {
    public function index(Request $request): View {
        $profile = $this->professionalProfile($request);
        $query = Withdrawal::query()->where('professional_id', $profile->getKey())
            ->with('wallet')->latest('requested_at')->latest('id');
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('currency')) {
            $currency = strtoupper($request->string('currency')->toString());
            $this->validateCurrency($currency);
            $query->where('currency', $currency);
        }
        $withdrawals = $query->paginate(10)->withQueryString();
        $currencies = Wallet::query()->where('professional_id', $profile->getKey())->orderBy('currency')->pluck('currency');
        return view('professional.withdrawals.index', compact('withdrawals','currencies'));
    }
    public function create(Request $request, WalletService $walletService): View {
        $profile = $this->professionalProfile($request);
        $currency = strtoupper((string) $request->query('currency', 'USD'));
        $this->validateCurrency($currency);
        $wallet = $walletService->getOrCreate($profile, $currency);
        $this->authorize('view', $wallet);
        $currencies = Wallet::query()->where('professional_id', $profile->getKey())->orderBy('currency')->pluck('currency');
        return view('professional.withdrawals.create', compact('wallet','currencies'));
    }
    public function store(StoreWithdrawalRequest $request, WithdrawalService $withdrawalService): RedirectResponse {
        $profile = $this->professionalProfile($request);
        $currency = strtoupper($request->string('currency')->toString());
        $wallet = Wallet::query()->where('professional_id', $profile->getKey())->where('currency', $currency)->firstOrFail();
        $this->authorize('view', $wallet);
        $withdrawal = $withdrawalService->request(
            professional: $profile,
            amount: $request->string('amount')->toString(),
            currency: $currency,
            provider: $request->string('provider')->toString(),
            destination: $request->string('destination')->toString(),
            idempotencyKey: $request->string('idempotency_key')->toString(),
        );
        return redirect()->route('professional.withdrawals.show', $withdrawal)
            ->with('status', 'Votre demande de retrait a bien été enregistrée et le montant est maintenant réservé.');
    }
    public function show(Request $request, Withdrawal $withdrawal): View {
        $this->authorize('view', $withdrawal);
        return view('professional.withdrawals.show', compact('withdrawal'));
    }
    private function professionalProfile(Request $request): ProfessionalProfile {
        return ProfessionalProfile::query()->where('user_id', $request->user()->getKey())->firstOrFail();
    }
    private function validateCurrency(string $currency): void {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) throw ValidationException::withMessages(['currency' => 'La devise sélectionnée est invalide.']);
    }
}
