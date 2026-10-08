@extends('layouts.client')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/client/dashboard.css')
    @endunless
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/client/dashboard.js')
    @endunless
@endpush

@section('page_eyebrow', 'VOTRE ACTIVITÉ')
@section('page_title', 'Tableau de bord')
@section('page_description', 'Retrouvez en un coup d’œil votre profil, vos favoris, vos adresses et les actions importantes.')

@section('page_actions')
    <a class="button button--primary" href="{{ url('/search') }}">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span>Rechercher un professionnel</span>
    </a>
@endsection

@section('content')
    <div class="client-dashboard" data-client-dashboard>
        <section class="client-dashboard__welcome" aria-labelledby="client-welcome-title">
            <div>
                <span class="client-dashboard__welcome-icon" aria-hidden="true">
                    <i class="fa-solid fa-hand-wave"></i>
                </span>
                <div>
                    <p class="client-dashboard__kicker">Bienvenue dans votre espace</p>
                    <h3 id="client-welcome-title">Bonjour, {{ $dashboard['profile']['user']['name'] }} 👋</h3>
                    <p>Nous vous aidons à trouver les bons professionnels pour vos projets.</p>
                </div>
            </div>
            <a class="button button--secondary" href="{{ url('/search') }}">
                <i class="fa-solid fa-compass" aria-hidden="true"></i>
                Explorer les services
            </a>
        </section>

        @if(count($dashboard['pending_actions']) > 0)
            <section class="client-dashboard__actions" aria-labelledby="pending-actions-title">
                <div class="client-dashboard__section-heading">
                    <div>
                        <span class="eyebrow">À FAIRE</span>
                        <h3 id="pending-actions-title">Quelques actions importantes</h3>
                    </div>
                </div>

                <div class="client-dashboard__action-list">
                    @foreach($dashboard['pending_actions'] as $action)
                        @php
                            $actionMap = [
                                'profile' => ['icon' => 'fa-user-pen', 'label' => 'Compléter mon profil', 'url' => url('/client/profile')],
                                'addresses' => ['icon' => 'fa-location-dot', 'label' => 'Gérer mes adresses', 'url' => url('/client/addresses')],
                                'notifications' => ['icon' => 'fa-bell', 'label' => 'Voir mes notifications', 'url' => url('/client/notifications')],
                            ];
                            $actionView = $actionMap[$action['type']] ?? ['icon' => 'fa-arrow-right', 'label' => 'Voir', 'url' => url('/client')];
                        @endphp

                        <a class="client-dashboard__action" href="{{ $actionView['url'] }}">
                            <span class="client-dashboard__action-icon" aria-hidden="true">
                                <i class="fa-solid {{ $actionView['icon'] }}"></i>
                            </span>
                            <span class="client-dashboard__action-copy">
                                <strong>{{ $action['message'] }}</strong>
                                <small>{{ $actionView['label'] }}</small>
                            </span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="client-dashboard__stats" aria-labelledby="client-stats-title">
            <div class="client-dashboard__section-heading">
                <div>
                    <span class="eyebrow">VUE D’ENSEMBLE</span>
                    <h3 id="client-stats-title">Votre espace en chiffres</h3>
                </div>
            </div>

            <div class="client-dashboard__stat-grid">
                <a class="client-dashboard__stat-card" href="{{ url('/client/favorites') }}">
                    <span class="client-dashboard__stat-icon client-dashboard__stat-icon--favorite" aria-hidden="true">
                        <i class="fa-solid fa-heart"></i>
                    </span>
                    <span>
                        <strong>{{ $dashboard['favorites']['count'] }}</strong>
                        <small>Professionnels favoris</small>
                    </span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <a class="client-dashboard__stat-card" href="{{ url('/client/addresses') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true">
                        <i class="fa-solid fa-location-dot"></i>
                    </span>
                    <span>
                        <strong>{{ $dashboard['addresses']['total'] }}</strong>
                        <small>Adresses enregistrées</small>
                    </span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <a class="client-dashboard__stat-card" href="{{ url('/client/notifications') }}">
                    <span class="client-dashboard__stat-icon client-dashboard__stat-icon--warning" aria-hidden="true">
                        <i class="fa-solid fa-bell"></i>
                    </span>
                    <span>
                        <strong>{{ $dashboard['notifications']['unread'] }}</strong>
                        <small>Notifications non lues</small>
                    </span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
            </div>
        </section>

        <div class="client-dashboard__grid">
            <section class="client-dashboard__card" aria-labelledby="default-address-title">
                <div class="client-dashboard__card-header">
                    <div>
                        <span class="eyebrow">ADRESSE</span>
                        <h3 id="default-address-title">Adresse principale</h3>
                    </div>
                    <span class="client-dashboard__card-icon" aria-hidden="true">
                        <i class="fa-solid fa-location-dot"></i>
                    </span>
                </div>

                @if($dashboard['addresses']['default'])
                    @php($address = $dashboard['addresses']['default'])
                    <div class="client-dashboard__address">
                        <strong>{{ $address['label'] }}</strong>
                        <p>{{ $address['recipient_name'] }} · {{ $address['contact_phone'] }}</p>
                        <p>{{ $address['address_line_1'] }}, {{ $address['neighborhood'] }}, {{ $address['commune'] }}</p>
                        <p>{{ $address['city'] }}, {{ $address['province'] }}</p>
                    </div>
                    <a class="text-link" href="{{ url('/client/addresses') }}">
                        Gérer mes adresses <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                @else
                    <div class="client-dashboard__empty">
                        <span class="client-dashboard__empty-icon" aria-hidden="true">
                            <i class="fa-solid fa-location-dot"></i>
                        </span>
                        <h4>Aucune adresse principale</h4>
                        <p>Ajoutez une adresse pour faciliter vos prochaines demandes.</p>
                        <a class="button button--secondary" href="{{ url('/client/addresses') }}">
                            Ajouter une adresse
                        </a>
                    </div>
                @endif
            </section>

            <section class="client-dashboard__card" aria-labelledby="profile-status-title">
                <div class="client-dashboard__card-header">
                    <div>
                        <span class="eyebrow">COMPTE</span>
                        <h3 id="profile-status-title">Mon profil</h3>
                    </div>
                    <span class="client-dashboard__card-icon" aria-hidden="true">
                        <i class="fa-solid fa-user"></i>
                    </span>
                </div>

                @if($dashboard['profile']['details'])
                    <div class="client-dashboard__profile-status">
                        <span class="client-dashboard__status-dot" aria-hidden="true"></span>
                        <div>
                            <strong>Profil disponible</strong>
                            <p>Vos informations personnelles sont enregistrées.</p>
                        </div>
                    </div>
                @else
                    <div class="client-dashboard__profile-status client-dashboard__profile-status--pending">
                        <span class="client-dashboard__status-dot" aria-hidden="true"></span>
                        <div>
                            <strong>Profil à compléter</strong>
                            <p>Ajoutez vos informations pour personnaliser votre expérience.</p>
                        </div>
                    </div>
                @endif

                <a class="text-link" href="{{ url('/client/profile') }}">
                    Gérer mon profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </section>
        </div>
    </div>
@endsection
