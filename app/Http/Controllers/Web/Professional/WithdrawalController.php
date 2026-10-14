<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\SupportTicketCategory;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Professional\Withdrawal\ProfessionalWithdrawalFilterRequest;
use App\Http\Requests\Professional\Withdrawal\ReportWithdrawalProblemRequest;
use App\Http\Requests\Wallet\StoreWithdrawalRequest;
use App\Models\ProfessionalProfile;
use App\Models\SupportTicket;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Admin\Support\SupportTicketService;
use App\Services\Wallet\WithdrawalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function index(ProfessionalWithdrawalFilterRequest $request): View
    {
        $profile = $this->professionalProfile($request);
        $filters = $request->filters();

        $query = Withdrawal::query()
            ->where('professional_id', $profile->getKey())
            ->with('wallet')
            ->latest('requested_at')
            ->latest('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (isset($filters['date_from'])) {
            $query->where('requested_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $query->where('requested_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filters['date_to'])->addDay());
        }

        $withdrawals = $query->paginate(10)->withQueryString();
        $currencies = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->orderBy('currency')
            ->pluck('currency');

        // Keep metrics grouped by currency; never add FC, USD, EUR, etc. together.
        $walletsByCurrency = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->orderBy('currency')
            ->get(['currency', 'available_balance', 'locked_balance', 'status'])
            ->keyBy('currency');

        $inProgressByCurrency = Withdrawal::query()
            ->where('professional_id', $profile->getKey())
            ->whereIn('status', [
                WithdrawalStatus::REQUESTED->value,
                WithdrawalStatus::PROCESSING->value,
                WithdrawalStatus::REVIEW_REQUIRED->value,
            ])
            ->selectRaw('currency, COUNT(*) as withdrawal_count, SUM(amount) as amount_total')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $successfulCountsByCurrency = Withdrawal::query()
            ->where('professional_id', $profile->getKey())
            ->where('status', WithdrawalStatus::SUCCEEDED->value)
            ->selectRaw('currency, COUNT(*) as successful_count')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');
        $canRequestWithdrawal = $walletsByCurrency->contains(
            static fn (Wallet $wallet): bool => strtolower((string) $wallet->status) === 'active',
        );

        return view('professional.withdrawals.index', compact(
            'withdrawals',
            'currencies',
            'filters',
            'walletsByCurrency',
            'inProgressByCurrency',
            'successfulCountsByCurrency',
            'canRequestWithdrawal',
        ));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $profile = $this->professionalProfile($request);
        $currency = strtoupper((string) $request->query('currency', 'USD'));
        $this->validateCurrency($currency);

        // A GET request must never create a financial account.
        $wallet = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->where('currency', $currency)
            ->first();

        if ($wallet === null) {
            return redirect()->route('professional.withdrawals')
                ->with('status', 'Aucun portefeuille n’existe pour cette devise. Un portefeuille est créé uniquement par un flux financier autorisé.');
        }

        $this->authorize('view', $wallet);

        $currencies = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->orderBy('currency')
            ->pluck('currency');

        return view('professional.withdrawals.create', compact('wallet', 'currencies'));
    }

    public function store(
        StoreWithdrawalRequest $request,
        WithdrawalService $withdrawalService,
    ): RedirectResponse {
        $profile = $this->professionalProfile($request);
        $currency = strtoupper($request->string('currency')->toString());

        $wallet = Wallet::query()
            ->where('professional_id', $profile->getKey())
            ->where('currency', $currency)
            ->firstOrFail();

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
            ->with('status', 'Votre demande a été enregistrée et le montant est réservé. Aucun paiement n’est considéré comme exécuté à ce stade.');
    }

    public function show(Request $request, Withdrawal $withdrawal): View
    {
        $this->authorize('view', $withdrawal);
        $withdrawal->load(['wallet', 'statusHistories.actor']);

        return view('professional.withdrawals.show', compact('withdrawal'));
    }

    public function cancel(
        Request $request,
        Withdrawal $withdrawal,
        WithdrawalService $withdrawalService,
    ): RedirectResponse {
        $this->authorize('cancel', $withdrawal);

        $withdrawalService->cancel(
            withdrawal: $withdrawal,
            actorUserId: (int) $request->user()->getKey(),
            reason: 'Annulation demandée par le professionnel avant le début du traitement.',
        );

        return redirect()->route('professional.withdrawals.show', $withdrawal)
            ->with('status', 'La demande a été annulée et la réservation a été libérée.');
    }

    public function reportProblem(
        ReportWithdrawalProblemRequest $request,
        Withdrawal $withdrawal,
        SupportTicketService $supportService,
    ): RedirectResponse {
        $this->authorize('view', $withdrawal);
        $this->authorize('create', SupportTicket::class);

        $reference = 'WD-'.str_pad((string) $withdrawal->getKey(), 8, '0', STR_PAD_LEFT);
        $ticket = $supportService->create($request->user(), [
            'withdrawal_id' => $withdrawal->getKey(),
            'subject' => 'Problème concernant le retrait '.$reference,
            'category' => SupportTicketCategory::PAYMENT->value,
            'priority' => 'normal',
            'body' => "Référence du retrait : {$reference}\nStatut actuel : ".$withdrawal->status->label()."\nMontant demandé : ".$withdrawal->amount.' '.$withdrawal->currency."\n\nDescription du problème :\n".$request->validated('body'),
        ], $request);

        return redirect()->route('professional.withdrawals.show', $withdrawal)
            ->with('status', 'Votre signalement a été transmis au support. Référence du ticket : #'.$ticket->getKey().'.');
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
