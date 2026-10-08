@extends('layouts.client')

@section('title', 'Mes favoris')

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
            <p class="client-module__eyebrow">DÉCOUVERTE</p>
            <h1>Mes favoris</h1>
            <p>Retrouvez les professionnels que vous souhaitez garder à portée de main.</p>
        </div>
        <a class="btn btn-primary" href="{{ url('/professionals') }}">
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            Découvrir des professionnels
        </a>
    </div>

    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if($favorites->isEmpty())
        <x-empty-state
            icon="fa-heart"
            title="Aucun favori pour le moment"
            message="Ajoutez un professionnel à vos favoris depuis l'annuaire pour le retrouver rapidement."
        />
    @else
        <div class="client-card-grid">
            @foreach($favorites as $favorite)
                @php($professional = $favorite->professionalProfile)
                @php($user = $professional->user)
                <article class="client-entity-card">
                    <div class="client-entity-card__top">
                        <div class="client-avatar">
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        </div>
                        <span class="status-badge status-badge--success">
                            Favori
                        </span>
                    </div>
                    <div>
                        <h2>{{ $user->name }}</h2>
                        <p class="client-entity-card__subtitle">{{ $professional->professional_title ?: 'Professionnel' }}</p>
                    </div>
                    <dl class="client-entity-card__meta">
                        <div>
                            <dt>Évaluation</dt>
                            <dd>
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                {{ number_format((float) $professional->rating_average, 1) }}
                                ({{ $professional->rating_count }})
                            </dd>
                        </div>
                        <div>
                            <dt>Disponibilité</dt>
                            <dd>{{ ucfirst(str_replace('_', ' ', data_get($professional->availability_status, 'value', $professional->availability_status ?? 'non définie'))) }}</dd>
                        </div>
                    </dl>
                    <div class="client-entity-card__actions">
                        <a class="btn btn-secondary" href="{{ url('/professionals') }}">Voir le profil</a>
                        <form method="POST" action="{{ route('client.favorites.destroy', $favorite) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger" type="submit">
                                <i class="fa-solid fa-heart-crack" aria-hidden="true"></i>
                                Retirer
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="client-pagination">{{ $favorites->links() }}</div>
    @endif
</div>
@endsection
