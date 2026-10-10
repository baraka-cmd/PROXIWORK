@extends('layouts.professional')

@section('title', 'Devis #' . $quotation->getKey() . ' — PROXIWORK')
@section('page_heading', 'Détail du devis')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Devis #{{ $quotation->getKey() }}</p>
            <h1>{{ $quotation->serviceRequest?->title ?? 'Demande de service' }}</h1>
            <p class="page-lead">Statut : {{ ucfirst($quotation->status->value) }}</p>
        </div>
        <div class="dashboard-page-header__actions"><a class="button button--ghost" href="{{ route('professional.quotes.index') }}">Retour aux devis</a></div>
    </div>

    <section class="surface-card content-card">
        <h2>Offre actuelle</h2>
        @if ($quotation->currentOffer)
            <dl class="details-list">
                <div><dt>Montant</dt><dd>{{ number_format((float) $quotation->currentOffer->amount, 2) }} {{ $quotation->currentOffer->currency }}</dd></div>
                <div><dt>Délai</dt><dd>{{ $quotation->currentOffer->duration_value }} {{ $quotation->currentOffer->duration_unit->value }}</dd></div>
                <div><dt>Valable jusqu'au</dt><dd>{{ $quotation->currentOffer->valid_until?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt>Description</dt><dd>{{ $quotation->currentOffer->description }}</dd></div>
                @if ($quotation->currentOffer->conditions)<div><dt>Conditions</dt><dd>{{ $quotation->currentOffer->conditions }}</dd></div>@endif
            </dl>
        @else
            <p>Aucune offre courante n'est disponible.</p>
        @endif
    </section>

    <section class="surface-card content-card">
        <h2>Historique des propositions</h2>
        @forelse ($quotation->offers as $offer)
            <article class="timeline-item">
                <h3>Version {{ $offer->version }} — {{ number_format((float) $offer->amount, 2) }} {{ $offer->currency }}</h3>
                <p>{{ $offer->description }}</p>
                <p class="table-secondary">Proposée le {{ $offer->created_at?->format('d/m/Y H:i') }}</p>
            </article>
        @empty
            <p>Aucune version historique.</p>
        @endforelse
    </section>
@endsection
