@extends('layouts.client')

@section('title', 'Mes adresses')

@push('head')
    @vite('resources/css/pages/client/addresses.css')
@endpush

@section('content')
<div class="client-addresses-page">
    <header class="client-module__header address-page-header">
        <div>
            <p class="client-module__eyebrow">CARNET D’ADRESSES</p>
            <h1>Mes adresses</h1>
            <p>Enregistrez les lieux d’intervention séparément de vos coordonnées personnelles. Une adresse choisie pour une demande ou une commande conserve son propre historique.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('client.addresses.create') }}">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Ajouter une adresse
        </a>
    </header>

    @if(session('success'))
        <div class="client-panel address-alert address-alert--success" role="status" aria-live="polite">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="client-panel address-alert address-alert--error" role="alert" aria-live="assertive">
            <strong>Cette opération n’a pas pu être effectuée.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($addresses->isEmpty())
        <x-empty-state
            icon="fa-location-dot"
            title="Votre carnet est encore vide"
            message="Ajoutez un lieu d’intervention pour le retrouver facilement lors de vos prochaines demandes. Une adresse physique n’est pas nécessaire pour tous les types de prestation."
        />
        <div class="address-empty-action">
            <a class="btn btn-primary" href="{{ route('client.addresses.create') }}">Ajouter mon premier lieu</a>
        </div>
    @else
        <div class="address-summary">
            <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $addresses->total() }} adresse(s) enregistrée(s)</span>
            <span>Les adresses restent privées à votre compte.</span>
        </div>

        <div class="address-grid">
            @foreach($addresses as $address)
                <article class="client-panel address-card">
                    <header class="address-card__header">
                        <div class="address-card__title">
                            <span class="address-card__icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                            <div>
                                <h2>{{ $address->label }}</h2>
                                @if($address->is_default)
                                    <span class="status-badge address-default-badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Adresse par défaut</span>
                                @else
                                    <span class="address-secondary-label">Adresse secondaire</span>
                                @endif
                            </div>
                        </div>
                    </header>

                    @if($address->recipient_name)
                        <p class="address-card__recipient"><i class="fa-regular fa-user" aria-hidden="true"></i> {{ $address->recipient_name }}</p>
                    @endif

                    <address class="address-card__location">
                        <strong>{{ $address->address_line_1 }}</strong>
                        @if($address->address_line_2)<span>{{ $address->address_line_2 }}</span>@endif
                        @if($address->neighborhood || $address->commune)
                            <span>{{ collect([$address->neighborhood, $address->commune])->filter()->join(', ') }}</span>
                        @endif
                        <span>{{ collect([$address->city, $address->province, $address->country_code])->filter()->join(', ') }}</span>
                        @if($address->landmark)<span><strong>Repère :</strong> {{ $address->landmark }}</span>@endif
                    </address>

                    @if($address->contact_phone)
                        <p class="address-card__contact"><i class="fa-solid fa-phone" aria-hidden="true"></i> {{ $address->contact_phone }}</p>
                    @endif

                    @if($address->access_instructions)
                        <div class="address-card__instructions">
                            <strong><i class="fa-solid fa-door-open" aria-hidden="true"></i> Instructions d’accès</strong>
                            <p>{{ $address->access_instructions }}</p>
                        </div>
                    @endif

                    @if($address->latitude !== null && $address->longitude !== null)
                        <p class="address-card__coordinates"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> Coordonnées GPS enregistrées</p>
                    @endif

                    <footer class="address-card__actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('client.addresses.edit', $address) }}">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Modifier
                        </a>
                        @unless($address->is_default)
                            <form method="POST" action="{{ route('client.addresses.default', $address) }}">
                                @csrf
                                <button class="btn btn-secondary btn-sm" type="submit">
                                    <i class="fa-solid fa-star" aria-hidden="true"></i> Définir par défaut
                                </button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('client.addresses.destroy', $address) }}" data-confirm-delete>
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Supprimer
                            </button>
                        </form>
                    </footer>
                </article>
            @endforeach
        </div>

        <div class="address-pagination">{{ $addresses->links() }}</div>
    @endif
</div>
@endsection
