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
@section('page_description', 'Retrouvez vos projets, les actions à suivre et les informations utiles à votre compte.')

@section('page_actions')
    <a class="button button--primary" href="{{ route('public.search') }}">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span>Découvrir les services</span>
    </a>
@endsection

@section('content')
    @php
        $chartMax = collect($dashboard['chart'])
            ->flatMap(fn ($month) => [$month['requests'], $month['orders']])
            ->max() ?: 1;

        $requestStatusLabels = [
            'draft' => 'Brouillon',
            'requested' => 'En attente du professionnel',
            'cancelled' => 'Annulée',
            'quoted' => 'Devis à consulter',
            'accepted' => 'Acceptée',
            'rejected' => 'Refusée',
        ];

        $orderStatusLabels = [
            'pending_payment' => 'Paiement attendu',
            'confirmed' => 'Confirmée',
            'in_progress' => 'En cours',
            'delivery_submitted' => 'Livraison à vérifier',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
            'disputed' => 'En résolution',
        ];
    @endphp

    <div class="client-dashboard" data-client-dashboard>
        <section class="client-dashboard__welcome" aria-labelledby="client-welcome-title">
            <div class="client-dashboard__welcome-copy">
                <span class="client-dashboard__welcome-icon" aria-hidden="true">
                    <i class="fa-solid fa-hand-wave"></i>
                </span>
                <div>
                    <p class="client-dashboard__kicker">VOTRE ESPACE PROXIWORK</p>
                    <h2 id="client-welcome-title">Bonjour, {{ $dashboard['profile']['user']['name'] }} <span aria-hidden="true">👋</span></h2>
                    <p>Reprenez vos projets là où vous les avez laissés, ou trouvez un professionnel pour votre prochain besoin.</p>
                </div>
            </div>
            <a class="button button--secondary" href="{{ route('public.professionals.index') }}">
                <i class="fa-solid fa-compass" aria-hidden="true"></i>
                Explorer les professionnels
            </a>
        </section>

        @if (count($dashboard['pending_actions']) > 0)
            <section class="client-dashboard__actions" aria-labelledby="pending-actions-title">
                <div class="client-dashboard__section-heading">
                    <div>
                        <span class="eyebrow">À NE PAS OUBLIER</span>
                        <h2 id="pending-actions-title">Les prochaines étapes</h2>
                        <p>Les actions qui peuvent vous aider à faire avancer vos projets.</p>
                    </div>
                    <span class="client-dashboard__count">{{ count($dashboard['pending_actions']) }} action(s)</span>
                </div>
                <div class="client-dashboard__action-list">
                    @foreach ($dashboard['pending_actions'] as $action)
                        @php
                            $actionMap = [
                                'profile' => ['icon' => 'fa-user-pen', 'label' => 'Compléter mon profil', 'url' => route('client.profile')],
                                'addresses' => ['icon' => 'fa-location-dot', 'label' => 'Gérer mes adresses', 'url' => url('/client/addresses')],
                                'notifications' => ['icon' => 'fa-bell', 'label' => 'Voir mes notifications', 'url' => url('/client/notifications')],
                                'quotes' => ['icon' => 'fa-file-invoice-dollar', 'label' => 'Examiner mes devis', 'url' => url('/client/quotes')],
                                'payments' => ['icon' => 'fa-credit-card', 'label' => 'Vérifier mes paiements', 'url' => url('/client/payments')],
                                'messages' => ['icon' => 'fa-comments', 'label' => 'Ouvrir mes messages', 'url' => url('/client/messages')],
                            ];
                            $actionView = $actionMap[$action['type']] ?? ['icon' => 'fa-arrow-right', 'label' => 'Voir mon espace', 'url' => route('client.dashboard')];
                        @endphp
                        <a class="client-dashboard__action {{ ($action['priority'] ?? 'normal') === 'high' ? 'client-dashboard__action--important' : '' }}" href="{{ $actionView['url'] }}">
                            <span class="client-dashboard__action-icon" aria-hidden="true"><i class="fa-solid {{ $actionView['icon'] }}"></i></span>
                            <span class="client-dashboard__action-copy">
                                <strong>{{ $action['message'] }}</strong>
                                <small>{{ $actionView['label'] }}</small>
                            </span>
                            @if (($action['priority'] ?? 'normal') === 'high')
                                <span class="client-dashboard__action-priority">À vérifier</span>
                            @else
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="client-dashboard__stats" aria-labelledby="client-stats-title">
            <div class="client-dashboard__section-heading">
                <div>
                    <span class="eyebrow">VUE D’ENSEMBLE</span>
                    <h2 id="client-stats-title">Suivi de vos projets</h2>
                    <p>Accédez directement aux éléments qui composent votre parcours.</p>
                </div>
            </div>
            <div class="client-dashboard__stat-grid client-dashboard__stat-grid--primary">
                <a class="client-dashboard__stat-card" href="{{ url('/client/requests') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['stats']['requests'] }}</strong><small>Demandes en cours</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
                <a class="client-dashboard__stat-card client-dashboard__stat-card--attention" href="{{ url('/client/quotes') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['stats']['quotes'] }}</strong><small>Devis à examiner</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
                <a class="client-dashboard__stat-card" href="{{ url('/client/orders') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-cart-shopping"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['stats']['orders'] }}</strong><small>Commandes actives</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
                <a class="client-dashboard__stat-card client-dashboard__stat-card--attention" href="{{ url('/client/payments') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['stats']['payments'] }}</strong><small>Paiements à vérifier</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
            </div>
        </section>

        <section class="client-dashboard__stats" aria-labelledby="client-account-stats-title">
            <div class="client-dashboard__section-heading">
                <div>
                    <span class="eyebrow">VOTRE COMPTE</span>
                    <h2 id="client-account-stats-title">Vos raccourcis personnels</h2>
                </div>
            </div>
            <div class="client-dashboard__stat-grid client-dashboard__stat-grid--secondary">
                <a class="client-dashboard__stat-card" href="{{ url('/client/messages') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-comments"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['stats']['messages'] }}</strong><small>Messages non lus</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
                <a class="client-dashboard__stat-card" href="{{ url('/client/favorites') }}">
                    <span class="client-dashboard__stat-icon client-dashboard__stat-icon--favorite" aria-hidden="true"><i class="fa-solid fa-heart"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['favorites']['count'] }}</strong><small>Favoris enregistrés</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
                <a class="client-dashboard__stat-card" href="{{ url('/client/addresses') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['addresses']['total'] }}</strong><small>Adresses enregistrées</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
                <a class="client-dashboard__stat-card" href="{{ url('/client/notifications') }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
                    <span class="client-dashboard__stat-copy"><strong>{{ $dashboard['notifications']['unread'] }}</strong><small>Notifications non lues</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square client-dashboard__stat-link" aria-hidden="true"></i>
                </a>
            </div>
        </section>

        <div class="client-dashboard__insight-grid">
            <section class="client-dashboard__card" aria-labelledby="client-chart-title">
                <div class="client-dashboard__card-header">
                    <div><span class="eyebrow">ÉVOLUTION</span><h2 id="client-chart-title">Activité des six derniers mois</h2><p>Volume mensuel de demandes et de commandes.</p></div>
                    <span class="client-dashboard__card-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
                </div>
                <div class="client-dashboard__chart" role="img" aria-label="Comparaison mensuelle des demandes et commandes sur les six derniers mois. Les valeurs exactes sont indiquées pour chaque mois ci-dessous.">
                    @foreach ($dashboard['chart'] as $month)
                        <div class="client-dashboard__chart-row">
                            <span class="client-dashboard__chart-month">{{ $month['label'] }}</span>
                            <div class="client-dashboard__chart-bars" aria-hidden="true">
                                <span class="client-dashboard__chart-bar client-dashboard__chart-bar--requests" style="width: {{ ($month['requests'] / $chartMax) * 100 }}%"></span>
                                <span class="client-dashboard__chart-bar client-dashboard__chart-bar--orders" style="width: {{ ($month['orders'] / $chartMax) * 100 }}%"></span>
                            </div>
                            <small><span>{{ $month['requests'] }}</span><span aria-hidden="true">/</span><span>{{ $month['orders'] }}</span></small>
                        </div>
                    @endforeach
                </div>
                <p class="client-dashboard__chart-legend"><span><i class="client-dashboard__legend-dot client-dashboard__legend-dot--requests" aria-hidden="true"></i> Demandes</span><span><i class="client-dashboard__legend-dot client-dashboard__legend-dot--orders" aria-hidden="true"></i> Commandes</span><span class="client-dashboard__chart-scale">Échelle commune : {{ $chartMax }} au maximum</span></p>
                <div class="client-dashboard__chart-data">
                    <h3 class="sr-only">Valeurs exactes du graphique</h3>
                    <ul>
                        @foreach ($dashboard['chart'] as $month)
                            <li>{{ $month['label'] }} : {{ $month['requests'] }} demande(s), {{ $month['orders'] }} commande(s).</li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="client-dashboard__card" aria-labelledby="client-activity-title">
                <div class="client-dashboard__card-header"><div><span class="eyebrow">HISTORIQUE</span><h2 id="client-activity-title">Activité récente</h2><p>Vos dernières demandes et commandes.</p></div></div>
                @if (count($dashboard['recent_activity']) > 0)
                    <div class="client-dashboard__activity-list">
                        @foreach ($dashboard['recent_activity'] as $activity)
                            @php
                                $statusLabel = $activity['type'] === 'order'
                                    ? ($orderStatusLabels[$activity['status']] ?? 'Statut mis à jour')
                                    : ($requestStatusLabels[$activity['status']] ?? 'Statut mis à jour');
                            @endphp
                            <a href="{{ $activity['url'] }}" class="client-dashboard__activity-item">
                                <span class="client-dashboard__activity-icon" aria-hidden="true"><i class="fa-solid {{ $activity['type'] === 'order' ? 'fa-cart-shopping' : 'fa-file-lines' }}"></i></span>
                                <span class="client-dashboard__activity-copy">
                                    <strong>{{ $activity['label'] }} · {{ $activity['title'] }}</strong>
                                    <small><span class="client-dashboard__status-pill">{{ $statusLabel }}</span>@if (!empty($activity['created_at']))<time datetime="{{ $activity['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($activity['created_at'])->format('d/m/Y') }}</time>@endif</small>
                                </span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="client-dashboard__empty">
                        <span class="client-dashboard__empty-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <h3>Aucune activité pour le moment</h3>
                        <p>Vos demandes et commandes apparaîtront ici au fil de vos projets.</p>
                        <a class="button button--secondary" href="{{ route('public.search') }}">Trouver un service</a>
                    </div>
                @endif
            </section>
        </div>

        <div class="client-dashboard__grid">
            <section class="client-dashboard__card" aria-labelledby="default-address-title">
                <div class="client-dashboard__card-header"><div><span class="eyebrow">ADRESSE</span><h2 id="default-address-title">Adresse principale</h2></div><span class="client-dashboard__card-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span></div>
                @if ($dashboard['addresses']['default'])
                    @php($address = $dashboard['addresses']['default'])
                    <div class="client-dashboard__address">
                        <strong>{{ $address['label'] }}</strong>
                        <p>{{ $address['recipient_name'] }} · {{ $address['contact_phone'] }}</p>
                        <p>{{ $address['address_line_1'] }}, {{ $address['neighborhood'] }}, {{ $address['commune'] }}</p>
                        <p>{{ $address['city'] }}, {{ $address['province'] }}</p>
                    </div>
                    <a class="text-link" href="{{ url('/client/addresses') }}">Gérer mes adresses <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @else
                    <div class="client-dashboard__empty">
                        <span class="client-dashboard__empty-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                        <h3>Aucune adresse principale</h3>
                        <p>Ajoutez une adresse pour faciliter vos prochaines demandes.</p>
                        <a class="button button--secondary" href="{{ url('/client/addresses') }}">Ajouter une adresse</a>
                    </div>
                @endif
            </section>

            <section class="client-dashboard__card" aria-labelledby="profile-status-title">
                <div class="client-dashboard__card-header"><div><span class="eyebrow">COMPTE</span><h2 id="profile-status-title">Mon profil</h2></div><span class="client-dashboard__card-icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span></div>
                @if ($dashboard['profile']['details'])
                    <div class="client-dashboard__profile-status"><span class="client-dashboard__status-dot" aria-hidden="true"></span><div><strong>Informations renseignées</strong><p>Votre profil contient des informations personnelles enregistrées.</p></div></div>
                @else
                    <div class="client-dashboard__profile-status client-dashboard__profile-status--pending"><span class="client-dashboard__status-dot" aria-hidden="true"></span><div><strong>Profil à compléter</strong><p>Ajoutez vos informations pour personnaliser votre expérience.</p></div></div>
                @endif
                <a class="text-link" href="{{ route('client.profile') }}">Gérer mon profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </section>
        </div>
    </div>
@endsection
