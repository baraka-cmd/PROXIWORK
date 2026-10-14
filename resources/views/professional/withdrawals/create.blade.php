@extends('layouts.professional')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/professional/wallet-withdrawals.css','resources/js/pages/professional/wallet-withdrawals.js'])
@endunless
@endpush
@section('page_eyebrow','FINANCES')
@section('page_title','Demander un retrait')
@section('page_description','Soumettez une demande. La réservation n’est pas une preuve que le paiement a été envoyé ou reçu.')
@section('content')
<div class="finance-page finance-page--narrow">
    @if($errors->any())<div class="alert alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(strtolower((string)$wallet->status) !== 'active')
        <div class="alert alert--warning" role="alert">Ce portefeuille n’est pas actif. Vous pouvez consulter ses informations, mais vous ne pouvez pas soumettre un retrait.</div>
    @endif
    <section class="data-card">
        <div class="wallet-withdrawal-summary">
            <span>Solde disponible</span>
            <strong>{{ \App\Support\MoneyFormatter::format((string)$wallet->available_balance) }} {{ $wallet->currency }}</strong>
            <small>Le serveur vérifiera à nouveau le solde et l’état du portefeuille lors de la soumission.</small>
        </div>
        <form method="POST" action="{{ route('professional.withdrawals.store') }}" class="finance-form" data-withdrawal-form>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key','web-'.\Illuminate\Support\Str::uuid()) }}">
            <div class="form-field">
                <label for="withdrawal-currency">Devise du retrait</label>
                <select id="withdrawal-currency" name="currency" required data-currency-select data-create-url="{{ route('professional.withdrawals.create') }}" @disabled(strtolower((string)$wallet->status) !== 'active')>
                    @foreach($currencies as $currency)<option value="{{ $currency }}" @selected($currency === $wallet->currency)>{{ $currency }}</option>@endforeach
                </select>
                @error('currency')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="form-field">
                <label for="withdrawal-amount">Montant à retirer</label>
                <div class="input-with-suffix">
                    <input id="withdrawal-amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ old('amount') }}" max="{{ $wallet->available_balance }}" required data-amount-input @disabled(strtolower((string)$wallet->status) !== 'active')>
                    <span>{{ $wallet->currency }}</span>
                </div>
                @error('amount')<small class="field-error">{{ $message }}</small>@enderror
                <small class="field-help">Le montant doit être positif et ne peut pas dépasser le solde disponible. Les limites commerciales supplémentaires restent à configurer.</small>
            </div>
            <div class="form-field">
                <label for="withdrawal-provider">Moyen de versement demandé</label>
                <select id="withdrawal-provider" name="provider" required @disabled(strtolower((string)$wallet->status) !== 'active')>
                    <option value="">Choisir un moyen</option>
                    <option value="mobile_money" @selected(old('provider') === 'mobile_money')>Mobile Money — traitement manuel</option>
                    <option value="bank_transfer" @selected(old('provider') === 'bank_transfer')>Virement bancaire — traitement manuel</option>
                </select>
                @error('provider')<small class="field-error">{{ $message }}</small>@enderror
                <small class="field-help">Ces choix indiquent la destination souhaitée. Ils ne signifient pas qu’un prestataire Mobile Money ou bancaire est intégré à PROXIWORK.</small>
            </div>
            <div class="form-field">
                <label for="withdrawal-destination">Destination de paiement</label>
                <input id="withdrawal-destination" name="destination" type="text" maxlength="191" value="{{ old('destination') }}" placeholder="Numéro ou identifiant du compte destinataire" autocomplete="off" required @disabled(strtolower((string)$wallet->status) !== 'active')>
                @error('destination')<small class="field-error">{{ $message }}</small>@enderror
                <small class="field-help">La destination sera chiffrée en base pour les nouvelles demandes et masquée dans l’historique. Vérifiez soigneusement les informations avant de confirmer.</small>
            </div>
            <div class="withdrawal-review">
                <span>Récapitulatif avant soumission</span>
                <strong><span data-review-amount>0,00</span> {{ $wallet->currency }}</strong>
                <dl class="withdrawal-review__details">
                    <div><dt>Montant brut demandé</dt><dd><span data-review-gross>0,00</span> {{ $wallet->currency }}</dd></div>
                    <div><dt>Frais</dt><dd>Non configurés</dd></div>
                    <div><dt>Montant net estimé</dt><dd>Indisponible tant que le barème de frais n’est pas configuré</dd></div>
                </dl>
                <small>Aucun frais ni délai n’est inventé. Si une politique tarifaire est ajoutée, le serveur devra recalculer et enregistrer les valeurs finales au moment de la demande.</small>
            </div>
            <label class="withdrawal-confirmation">
                <input type="checkbox" name="confirm_request" value="1" @checked(old('confirm_request')) required @disabled(strtolower((string)$wallet->status) !== 'active')>
                <span>Je confirme le montant, le moyen et la destination. Je comprends que cette action réserve les fonds et ne confirme pas l’exécution du paiement.</span>
            </label>
            @error('confirm_request')<small class="field-error">{{ $message }}</small>@enderror
            <div class="form-actions">
                <a class="button button--secondary" href="{{ route('professional.wallet',['currency'=>$wallet->currency]) }}">Retour au portefeuille</a>
                <button class="button button--primary" type="submit" data-withdrawal-submit @disabled(strtolower((string)$wallet->status) !== 'active' || (float)$wallet->available_balance <= 0)>
                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Réserver le montant
                </button>
            </div>
        </form>
    </section>
</div>
@endsection
