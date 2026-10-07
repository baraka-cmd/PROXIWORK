@extends('layouts.professional')
@section('page_eyebrow','FINANCES')
@section('page_title','Demander un retrait')
@section('page_description','Réservez une partie de votre solde disponible. Le traitement réel du fournisseur sera effectué séparément.')
@section('content')
<div class="finance-page finance-page--narrow"><section class="data-card">
<div class="wallet-withdrawal-summary"><span>Solde disponible</span><strong>{{ number_format((float)$wallet->available_balance,2,',',' ') }} {{ $wallet->currency }}</strong></div>
@if($errors->any())<div class="alert alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('professional.withdrawals.store') }}" class="finance-form" data-withdrawal-form>
@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key','web-'.\Illuminate\Support\Str::uuid()) }}">
<div class="form-field"><label for="withdrawal-currency">Devise</label><select id="withdrawal-currency" name="currency" required>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected($currency === $wallet->currency)>{{ $currency }}</option>@endforeach</select>@error('currency')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-field"><label for="withdrawal-amount">Montant</label><div class="input-with-suffix"><input id="withdrawal-amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ old('amount') }}" max="{{ $wallet->available_balance }}" required data-amount-input><span>{{ $wallet->currency }}</span></div>@error('amount')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-field"><label for="withdrawal-provider">Canal de retrait</label><select id="withdrawal-provider" name="provider" required><option value="mobile_money" @selected(old('provider') === 'mobile_money')>Mobile Money</option><option value="bank_transfer" @selected(old('provider') === 'bank_transfer')>Virement bancaire</option></select><small class="field-help">La demande est enregistrée par PROXIWORK. Aucun fournisseur externe n’est simulé ici.</small></div>
<div class="form-field"><label for="withdrawal-destination">Destination</label><input id="withdrawal-destination" name="destination" type="text" maxlength="191" value="{{ old('destination') }}" placeholder="Numéro Mobile Money ou coordonnées du compte" autocomplete="off" required>@error('destination')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="withdrawal-review"><span>Montant réservé après validation</span><strong><span data-review-amount>0,00</span> {{ $wallet->currency }}</strong><small>Le serveur recalculera et vérifiera le solde au moment de l’enregistrement.</small></div>
<div class="form-actions"><a class="button button--secondary" href="{{ route('professional.wallet',['currency'=>$wallet->currency]) }}">Annuler</a><button class="button button--primary" type="submit" data-withdrawal-submit><i class="fa-solid fa-lock" aria-hidden="true"></i> Réserver le montant</button></div>
</form></section></div>
@endsection
