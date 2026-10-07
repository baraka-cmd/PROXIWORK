@extends('layouts.client')
@section('title','Mes devis')
@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
<div class="client-module"><div class="client-module__header"><div><p class="client-module__eyebrow">ACTIVITÉ COMMERCIALE</p><h1>Mes devis</h1><p>Comparez les propositions reçues et poursuivez la négociation depuis un seul espace.</p></div></div>
@if($quotes->isEmpty())<x-empty-state icon="fa-file-invoice-dollar" title="Aucun devis" message="Les devis reçus à la suite de vos demandes apparaîtront ici." />@else<div class="client-list">@foreach($quotes as $quote)<article class="client-list-item"><div class="client-list-item__main"><div class="client-list-item__icon"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i></div><div><h2>Devis #{{ $quote->id }}</h2><p>{{ $quote->serviceRequest?->service?->title }} · {{ $quote->serviceRequest?->professional?->user?->name }}</p><small>Mis à jour le {{ optional($quote->updated_at)->format('d/m/Y à H:i') }}</small></div></div><div class="client-list-item__side">@if($quote->currentOffer)<strong>{{ number_format((float)$quote->currentOffer->amount,2,',',' ') }} {{ $quote->currentOffer->currency }}</strong>@endif<span class="status-badge status-badge--{{ $quote->status->value }}">{{ ucfirst($quote->status->value) }}</span><a class="btn btn-secondary btn-sm" href="{{ route('client.quotes.show',$quote) }}">Voir</a></div></article>@endforeach</div><div class="client-pagination">{{ $quotes->links() }}</div>@endif</div>
@endsection