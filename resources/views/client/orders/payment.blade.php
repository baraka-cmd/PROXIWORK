@extends('layouts.client')

@section('title', 'Payer la commande')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush

<div class="client-module client-payment-checkout">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">RÉCAPITULATIF AVANT PAIEMENT</p>
            <h1>Régler {{ $order->order_number ?? '#'.$order->id }}</h1>
            <p>{{ $order->items->first()?->service_title ?? 'Prestation de service' }}</p>
        </div>
    </header>

    @if($errors->any())
        <div class="payment-feedback payment-feedback--error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <aside class="payment-simulation-notice" role="note">
        <i class="fa-solid fa-flask" aria-hidden="true"></i>
        <p><strong>Vous êtes en mode simulation.</strong> Aucun argent réel ne sera transféré. N’entrez aucun numéro de carte réel, code PIN Mobile Money ni secret bancaire.</p>
    </aside>

    <section class="client-panel payment-checkout">
        <div class="payment-checkout__summary">
            <span class="client-module__eyebrow">MONTANT FIXÉ PAR LA COMMANDE</span>
            <strong class="payment-amount">{{ number_format((float) $order->total, 2, ',', ' ') }} <span>{{ $order->currency }}</span></strong>
            <p>Le montant et la devise sont récupérés côté serveur depuis la commande. Le formulaire ne permet pas de les modifier.</p>
        </div>

        @if($simulationEnabled)
            <form method="POST" action="{{ route('client.payments.store', $order) }}" data-payment-form>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                <label for="payment-method">Moyen de paiement de démonstration
                    <select id="payment-method" name="method" required>
                        <option value="">Choisir un moyen simulé</option>
                        @foreach($methods as $method)
                            <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ match($method->value) {'mobile_money' => 'Mobile Money simulé', 'card' => 'Carte de test', 'bank_transfer' => 'Virement de démonstration', default => $method->value} }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="provider" value="{{ \App\Enums\PaymentProvider::FAKE->value }}">
                <div class="payment-checkout__provider"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong>Passerelle</strong><small>Simulateur PROXIWORK — aucune connexion à Airtel Money, M-Pesa, Orange Money, Visa, Mastercard ou à une banque.</small></span></div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-flask" aria-hidden="true"></i> Lancer la simulation</button>
            </form>
        @else
            <div class="payment-feedback payment-feedback--error" role="status">
                <strong>Simulation désactivée dans cet environnement.</strong>
                <p>Aucun prestataire de paiement réel n’est actuellement configuré. Le paiement ne peut donc pas être lancé ici.</p>
            </div>
        @endif
    </section>
    <a class="btn btn-secondary" href="{{ route('client.orders.show', $order) }}">Retour à la commande</a>
</div>
@endsection
