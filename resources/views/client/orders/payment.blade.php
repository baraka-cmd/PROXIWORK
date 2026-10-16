@extends('layouts.client')

@section('title', 'Payer la commande')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/orders-payments.js')
@endpush

<div class="client-module client-payment-checkout">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">ESPACE CLIENT · PAIEMENT</p>
            <h1>Récapitulatif de commande</h1>
            <p>{{ $order->items->first()?->service_title ?? 'Prestation de service' }} · Référence <strong>{{ $order->order_number ?? '#'.$order->id }}</strong></p>
        </div>
        <span class="status-badge status-badge--pending">Paiement à effectuer</span>
    </header>

    @if($errors->any())
        <div class="client-order-error-summary payment-feedback payment-feedback--error" role="alert" tabindex="-1" data-order-error-summary>
            <h2>Le paiement n’a pas pu être lancé</h2>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <aside class="payment-simulation-notice" role="note">
        <i class="fa-solid fa-flask" aria-hidden="true"></i>
        <p><strong>Vous êtes en mode simulation.</strong> Aucun argent réel ne sera transféré. N’entrez aucun numéro de carte réel, code PIN Mobile Money ni secret bancaire.</p>
    </aside>

    <section class="client-panel payment-checkout">
        <div class="payment-checkout__summary">
            <span class="client-module__eyebrow">MONTANT FIXÉ PAR LA COMMANDE</span>
            <strong class="payment-amount">{{ number_format((float) $order->total, 2, ',', ' ') }} <span>{{ $order->currency }}</span></strong>
            <p>Le montant et la devise proviennent de la commande enregistrée côté serveur. Ils ne peuvent pas être modifiés depuis ce formulaire.</p>
            <p class="client-order-form-hint"><i class="fa-solid fa-lock" aria-hidden="true"></i> Référence : {{ $order->order_number ?? '#'.$order->id }} · Aucun paiement ne sera considéré comme réussi avant confirmation de son état enregistré.</p>
        </div>

        @if($simulationEnabled)
            <form method="POST" action="{{ route('client.payments.store', $order) }}" data-payment-form>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <fieldset class="payment-method-fieldset">
                    <legend>Moyen de paiement de démonstration</legend>
                    <p class="client-order-form-hint">Choisissez une option de test. Aucune coordonnée bancaire ou donnée secrète n’est demandée.</p>
                    <div class="payment-method-options">
                        @foreach($methods as $method)
                            <label class="payment-method-option">
                                <input type="radio" name="method" value="{{ $method->value }}" @checked(old('method') === $method->value) required>
                                <span>
                                    <strong>{{ match($method->value) {'mobile_money' => 'Mobile Money', 'card' => 'Carte de test', 'bank_transfer' => 'Virement', default => 'Autre moyen de test'} }}</strong>
                                    <small>{{ match($method->value) {'mobile_money' => 'Simulation uniquement', 'card' => 'Aucune carte réelle', 'bank_transfer' => 'Aucun transfert bancaire', default => 'Mode démonstration'} }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <input type="hidden" name="provider" value="{{ \App\Enums\PaymentProvider::FAKE->value }}">
                <div class="payment-checkout__provider"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong>Passerelle</strong><small>Simulateur PROXIWORK — aucune connexion à Airtel Money, M-Pesa, Orange Money, Visa, Mastercard ou à une banque.</small></span></div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-flask" aria-hidden="true"></i> Lancer la simulation</button>
                <p class="client-order-form-hint">Le résultat dépendra de la réponse enregistrée par le simulateur. Cette action ne déclenche aucun transfert réel.</p>
            </form>
        @else
            <div class="payment-feedback payment-feedback--error" role="status">
                <strong>Simulation désactivée dans cet environnement.</strong>
                <p>Aucun prestataire de paiement réel n’est actuellement configuré. Le paiement ne peut donc pas être lancé ici.</p>
            </div>
        @endif
    </section>
    <a class="btn btn-secondary" href="{{ route('client.orders.show', $order) }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à la commande</a>
</div>
@endsection
