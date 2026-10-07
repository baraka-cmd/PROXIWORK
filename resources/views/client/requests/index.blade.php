@extends('layouts.client')

@section('title', 'Mes demandes')

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
<div class="client-module">
    <div class="client-module__header">
        <div>
            <p class="client-module__eyebrow">ACTIVITÉ</p>
            <h1>Mes demandes</h1>
            <p>Suivez les demandes envoyées aux professionnels et leur évolution.</p>
        </div>
    </div>

    <div class="client-filter-bar" role="group" aria-label="Filtrer les demandes">
        @foreach(['' => 'Toutes', 'draft' => 'Brouillons', 'requested' => 'Envoyées', 'quoted' => 'Devis reçus', 'accepted' => 'Acceptées', 'rejected' => 'Refusées', 'cancelled' => 'Annulées'] as $value => $label)
            <a class="client-filter {{ $selectedStatus === $value ? 'is-active' : '' }}"
               href="{{ $value === '' ? route('client.requests.index') : route('client.requests.index', ['status' => $value]) }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($requests->isEmpty())
        <x-empty-state
            icon="fa-file-lines"
            title="Aucune demande"
            message="Vos demandes de services apparaîtront ici dès qu'elles seront créées."
        />
    @else
        <div class="client-list">
            @foreach($requests as $item)
                <article class="client-list-item">
                    <div class="client-list-item__main">
                        <div class="client-list-item__icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                        <div>
                            <h2>{{ $item->title }}</h2>
                            <p>{{ $item->service?->title }} · {{ $item->professional?->user?->name }}</p>
                            <small>Créée le {{ optional($item->created_at)->format('d/m/Y à H:i') }}</small>
                        </div>
                    </div>
                    <div class="client-list-item__side">
                        <span class="status-badge status-badge--{{ $item->status->value }}">{{ ucfirst($item->status->value) }}</span>
                        <a class="btn btn-secondary btn-sm" href="{{ route('client.requests.show', $item) }}">Détails</a>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="client-pagination">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
