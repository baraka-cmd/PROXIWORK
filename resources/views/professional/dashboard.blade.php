@extends('layouts.professional')

@section('title', 'Tableau de bord professionnel — PROXIWORK')
@section('meta_description', 'Pilotez vos demandes, devis, commandes, revenus et services sur PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/dashboard.css', 'resources/js/pages/professional/dashboard.js'])
    @endunless
@endpush

@section('page_eyebrow', 'CENTRE DE PILOTAGE')
@section('page_title', 'Tableau de bord')
@section('page_description', 'Les actions utiles et les chiffres réels de votre activité professionnelle.')

@php
    $profile = $dashboard['profile'];
    $services = $dashboard['services'];
    $engagement = $dashboard['engagement'];
    $notifications = $dashboard['notifications'];
    $operations = $dashboard['operations'];
    $finance = $dashboard['finance'];
    $pendingActions = $dashboard['pending_actions'];
    $displayName = $profile['user']['name'] ?? 'Professionnel';
    $initials = collect(preg_split('/\s+/', trim($displayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $verificationLabels = [
        'pending' => 'En attente',
        'under_review' => 'En cours de vérification',
        'verified' => 'Vérifié',
        'rejected' => 'Rejeté',
    ];
    $availabilityLabels = [
        'unknown' => 'Non renseignée',
        'available' => 'Disponible',
        'unavailable' => 'Indisponible',
    ];
    $accountLabels = [
        'active' => 'Compte actif',
        'suspended' => 'Compte suspendu',
        'pending' => 'Compte en attente',
        'closed' => 'Compte fermé',
    ];
    $verification = $profile['verification_status'] ?? 'pending';
    $availability = $profile['availability_status'] ?? 'unknown';
    $periodOptions = [
        '7d' => '7 derniers jours',
        '30d' => '30 derniers jours',
        '90d' => '90 derniers jours',
        '12m' => '12 derniers mois',
    ];
    $period = $dashboard['period'];
    $periodEnd = $dashboard['generated_at']->format('Y-m-d');
    $periodStart = $period === '12m'
        ? $dashboard['generated_at']->startOfMonth()->subMonths(11)->format('Y-m-d')
        : $dashboard['generated_at']->startOfDay()->subDays(['7d' => 6, '30d' => 29, '90d' => 89][$period] ?? 29)->format('Y-m-d');
    $money = static fn ($amount) => number_format((float) $amount, 2, ',', ' ');
@endphp

@section('page_actions')
    <a class="button button--secondary" href="{{ route('professional.requests.index', ['status' => 'requested']) }}">
        <i class="fa-solid fa-inbox" aria-hidden="true"></i>
        <span>Demandes à traiter</span>
    </a>
    <a class="button button--primary" href="{{ route('professional.services.create') }}">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span>Créer un service</span>
    </a>
@endsection

@section('content')
    <div
        class="professional-dashboard"
        data-professional-dashboard
        data-dashboard-chart-data-id="professional-dashboard-chart-data"
    >
        <script type="application/json" id="professional-dashboard-chart-data">@json($dashboard['charts'])</script>

        <section class="professional-dashboard__welcome" aria-labelledby="professional-dashboard-welcome-title">
            <div class="professional-dashboard__identity">
                <div class="professional-dashboard__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'P' }}</div>
                <div class="professional-dashboard__welcome-copy">
                    <span class="professional-dashboard__eyebrow">ESPACE PROFESSIONNEL</span>
                    <h2 id="professional-dashboard-welcome-title">Bonjour {{ $displayName }} <span aria-hidden="true">👋</span></h2>
                    <p>{{ $profile['professional_title'] ?: 'Complétez votre profil et présentez vos services pour développer votre activité.' }}</p>
                    <div class="professional-dashboard__badges">
                        <span class="professional-dashboard__status {{ ($profile['user']['account_status'] ?? '') === 'active' ? 'is-positive' : 'is-caution' }}">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i>
                            {{ $accountLabels[$profile['user']['account_status'] ?? ''] ?? 'État du compte non renseigné' }}
                        </span>
                        <span class="professional-dashboard__status {{ $verification === 'verified' ? 'is-positive' : 'is-caution' }}">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            {{ $verificationLabels[$verification] ?? 'Vérification non renseignée' }}
                        </span>
                        <span class="professional-dashboard__status">
                            <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                            {{ $dashboard['generated_at']->locale(app()->getLocale())->translatedFormat('l d F Y') }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="professional-dashboard__completion">
                <div class="professional-dashboard__completion-heading">
                    <span>Complétude du profil</span>
                    <strong>{{ $profile['profile_completion'] }}%</strong>
                </div>
                <div class="professional-dashboard__progress" role="progressbar" aria-label="Complétude du profil" aria-valuenow="{{ $profile['profile_completion'] }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $profile['profile_completion'] }}%"></span>
                </div>
                @if ($profile['missing_fields'] !== [])
                    <p>À compléter : {{ implode(', ', $profile['missing_fields']) }}.</p>
                    <a href="{{ route('professional.profile.edit') }}">Compléter mon profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @else
                    <p>Les champs suivis par cet indicateur sont renseignés.</p>
                    <a href="{{ route('professional.profile') }}">Consulter mon profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @endif
            </div>
        </section>

        <section class="professional-dashboard-card professional-dashboard-card--priority" aria-labelledby="professional-priority-title">
            <div class="professional-dashboard-card__header">
                <div>
                    <span class="professional-dashboard-card__eyebrow">VOTRE PROCHAINE ÉTAPE</span>
                    <h2 id="professional-priority-title">À traiter maintenant</h2>
                    <p>Les éléments qui demandent une action ou une vérification de votre part.</p>
                </div>
                <span class="professional-dashboard__priority-count">{{ $pendingActions->count() }} élément{{ $pendingActions->count() > 1 ? 's' : '' }}</span>
            </div>

            @if ($pendingActions->isEmpty())
                <div class="professional-empty-state professional-empty-state--success">
                    <span class="professional-empty-state__icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                    <div>
                        <strong>Aucune action prioritaire détectée</strong>
                        <p>Vous pouvez consulter vos indicateurs ou continuer à développer votre catalogue.</p>
                    </div>
                    <a class="button button--secondary" href="{{ route('professional.services.index') }}">Gérer mes services</a>
                </div>
            @else
                <ul class="professional-action-list">
                    @foreach ($pendingActions as $action)
                        <li class="professional-action-list__item {{ ($action['priority'] ?? 'normal') === 'high' ? 'is-priority' : '' }}">
                            <span class="professional-action-list__icon" aria-hidden="true">
                                @switch($action['type'])
                                    @case('requests') <i class="fa-solid fa-inbox"></i> @break
                                    @case('quotes') <i class="fa-solid fa-file-invoice-dollar"></i> @break
                                    @case('orders') <i class="fa-solid fa-list-check"></i> @break
                                    @case('messages') <i class="fa-solid fa-comments"></i> @break
                                    @case('notifications') <i class="fa-solid fa-bell"></i> @break
                                    @case('documents') <i class="fa-solid fa-id-card"></i> @break
                                    @case('profile') <i class="fa-solid fa-user-shield"></i> @break
                                    @default <i class="fa-solid fa-briefcase"></i>
                                @endswitch
                            </span>
                            <div class="professional-action-list__copy">
                                <strong>
                                    @switch($action['type'])
                                        @case('requests') Demandes à examiner @break
                                        @case('quotes') Devis nécessitant votre réponse @break
                                        @case('orders') Commandes à suivre @break
                                        @case('messages') Messages non lus @break
                                        @case('notifications') Notifications non lues @break
                                        @case('documents') Vérification professionnelle @break
                                        @case('profile') État du profil @break
                                        @default Services professionnels
                                    @endswitch
                                </strong>
                                <p>{{ $action['message'] }}</p>
                            </div>
                            <a class="professional-action-list__link" href="{{ $action['url'] }}" aria-label="Traiter : {{ $action['message'] }}">
                                <span>Ouvrir</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="professional-dashboard-section" aria-labelledby="operations-title">
            <div class="professional-dashboard-section__heading">
                <div>
                    <span class="professional-dashboard-card__eyebrow">ACTIVITÉ COMMERCIALE</span>
                    <h2 id="operations-title">Votre activité en un coup d’œil</h2>
                </div>
                <form class="professional-dashboard__period-form" method="GET" action="{{ route('professional.dashboard') }}">
                    <label for="dashboard-period">Période des indicateurs historiques</label>
                    <select id="dashboard-period" name="period" onchange="this.form.submit()">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="button button--secondary" type="submit">Appliquer</button></noscript>
                </form>
            </div>

            <div class="professional-dashboard__stats professional-dashboard__stats--four">
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.requests.index', ['status' => 'requested']) }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--primary"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Demandes à traiter</span><strong>{{ $operations['requests_to_handle'] }}</strong><small>Demandes en attente du professionnel</small></span>
                    <i class="professional-stat-card__arrow fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.quotes.index') }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--accent"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Devis à traiter</span><strong>{{ $operations['quotations_requiring_action'] }}</strong><small>Contre-propositions du client à examiner</small></span>
                    <i class="professional-stat-card__arrow fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.orders.index') }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--info"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Commandes actives</span><strong>{{ $operations['active_orders'] }}</strong><small>À suivre selon leur état réel</small></span>
                    <i class="professional-stat-card__arrow fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.orders.index', ['status' => 'completed']) }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Prestations terminées</span><strong>{{ $operations['completed_orders'] }}</strong><small>{{ $dashboard['period_label'] }}</small></span>
                    <i class="professional-stat-card__arrow fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
            </div>

            <div class="professional-dashboard__stats professional-dashboard__stats--three">
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.reviews') }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--accent"><i class="fa-solid fa-star" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Note moyenne</span>
                        @if ($engagement['rating_count'] > 0)
                            <strong>{{ number_format((float) $engagement['rating_average'], 1, ',', ' ') }} <span class="professional-stat-card__rating" aria-hidden="true">★</span></strong>
                            <small>{{ $engagement['rating_count'] }} avis publiés</small>
                        @else
                            <strong class="professional-stat-card__empty-value">—</strong><small>Aucun avis pour le moment</small>
                        @endif
                    </span>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.reviews') }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--info"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Avis reçus</span><strong>{{ $engagement['rating_count'] }}</strong><small>Avis actuellement publiés</small></span>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.messages', ['status' => 'unread']) }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--primary"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Conversations non lues</span><strong>{{ $notifications['messages_unread'] }}</strong><small>Conversations accessibles avec messages non lus</small></span>
                </a>
            </div>
        </section>

        <section class="professional-dashboard-section" aria-labelledby="finance-title">
            <div class="professional-dashboard-section__heading">
                <div>
                    <span class="professional-dashboard-card__eyebrow">FINANCES</span>
                    <h2 id="finance-title">Revenus et portefeuille</h2>
                    <p>Les montants sont présentés séparément par devise et selon les écritures financières enregistrées.</p>
                </div>
            </div>
            <div class="professional-dashboard__stats professional-dashboard__stats--three">
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.revenues.index', ['from' => $periodStart, 'to' => $periodEnd]) }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--success"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body">
                        <span>Revenus nets confirmés</span>
                        @forelse ($finance['revenues'] as $revenue)
                            <strong class="professional-stat-card__money">{{ $money($revenue['net_amount']) }} <small>{{ $revenue['currency'] }}</small></strong>
                        @empty
                            <strong class="professional-stat-card__empty-value">—</strong><small>Aucun revenu confirmé sur la période</small>
                        @endforelse
                        <small>{{ $dashboard['period_label'] }}</small>
                    </span>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.wallet') }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--primary"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body">
                        <span>Solde disponible au retrait</span>
                        @forelse ($finance['wallets'] as $wallet)
                            <strong class="professional-stat-card__money">{{ $money($wallet['available_balance']) }} <small>{{ $wallet['currency'] }}</small></strong>
                        @empty
                            <strong class="professional-stat-card__empty-value">—</strong><small>Aucun portefeuille existant à afficher</small>
                        @endforelse
                        <small>Solde disponible enregistré dans le portefeuille</small>
                    </span>
                </a>
                <a class="professional-stat-card professional-stat-card--link" href="{{ route('professional.withdrawals', ['status' => 'requested']) }}">
                    <span class="professional-stat-card__icon professional-stat-card__icon--accent"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i></span>
                    <span class="professional-stat-card__body"><span>Retraits en cours</span><strong>{{ $operations['pending_withdrawals'] }}</strong><small>Demandés ou en traitement</small></span>
                </a>
            </div>
        </section>

        <section class="professional-dashboard-section" aria-labelledby="charts-title">
            <div class="professional-dashboard-section__heading">
                <div>
                    <span class="professional-dashboard-card__eyebrow">ANALYSE</span>
                    <h2 id="charts-title">Comprendre l’évolution de votre activité</h2>
                    <p>Période : {{ $dashboard['period_label'] }}. Les graphiques utilisent les dates et états enregistrés par PROXIWORK.</p>
                </div>
            </div>

            <div class="professional-dashboard__charts">
                <article class="professional-dashboard-card professional-chart-card professional-chart-card--wide">
                    <div class="professional-dashboard-card__header">
                        <div><span class="professional-dashboard-card__eyebrow">TENDANCE</span><h3>Évolution de l’activité</h3><p>Demandes reçues, commandes créées et prestations terminées.</p></div>
                    </div>
                    @if (array_sum($dashboard['charts']['activity']['requests']) + array_sum($dashboard['charts']['activity']['orders']) + array_sum($dashboard['charts']['activity']['completed']) > 0)
                        <div class="professional-chart-container"><canvas data-chart="activity" role="img" aria-label="Graphique de l’évolution des demandes, commandes et prestations terminées"></canvas></div>
                    @else
                        <div class="professional-empty-state"><span class="professional-empty-state__icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><strong>Pas encore assez d’historique</strong><p>Les données apparaîtront ici dès que votre activité enregistrera des demandes ou des commandes.</p></div>
                    @endif
                </article>

                <article class="professional-dashboard-card professional-chart-card">
                    <div class="professional-dashboard-card__header">
                        <div><span class="professional-dashboard-card__eyebrow">FINANCES</span><h3>Évolution des revenus nets</h3><p>Commissions comptabilisées au statut confirmé, par devise.</p></div>
                        <a href="{{ route('professional.revenues.index', ['from' => $periodStart, 'to' => $periodEnd]) }}">Détails <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    @if ($dashboard['charts']['revenue']['series'] !== [])
                        <div class="professional-chart-currency">
                            @foreach ($dashboard['charts']['revenue']['series'] as $revenueSeries)
                                <div>
                                    <h4>Revenus en {{ $revenueSeries['label'] }}</h4>
                                    <div class="professional-chart-container"><canvas data-chart="revenue" data-currency="{{ $revenueSeries['label'] }}" role="img" aria-label="Évolution des revenus nets en {{ $revenueSeries['label'] }}"></canvas></div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="professional-empty-state"><span class="professional-empty-state__icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span><strong>Aucun revenu confirmé</strong><p>Le graphique apparaîtra lorsque des commissions auront été comptabilisées.</p></div>
                    @endif
                </article>

                <article class="professional-dashboard-card professional-chart-card">
                    <div class="professional-dashboard-card__header">
                        <div><span class="professional-dashboard-card__eyebrow">COMMANDES</span><h3>Répartition par état</h3><p>Vue de l’ensemble des commandes actuellement enregistrées.</p></div>
                        <a href="{{ route('professional.orders.index') }}">Voir les commandes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    @if (collect($dashboard['charts']['order_distribution'])->sum('count') > 0)
                        <div class="professional-chart-container professional-chart-container--compact"><canvas data-chart="orders" role="img" aria-label="Répartition des commandes par état"></canvas></div>
                        <p class="professional-chart-hint">Sélectionnez un segment pour ouvrir les commandes de cet état.</p>
                    @else
                        <div class="professional-empty-state"><span class="professional-empty-state__icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><strong>Aucune commande</strong><p>Les états de commande seront visibles ici après la création de votre première commande.</p></div>
                    @endif
                </article>

                <article class="professional-dashboard-card professional-chart-card">
                    <div class="professional-dashboard-card__header">
                        <div><span class="professional-dashboard-card__eyebrow">CATALOGUE</span><h3>Services les plus demandés</h3><p>Classement par demandes reçues sur la période sélectionnée.</p></div>
                        <a href="{{ route('professional.services.index') }}">Gérer les services <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    @if ($dashboard['charts']['top_services'] !== [])
                        <div class="professional-chart-container"><canvas data-chart="services" role="img" aria-label="Classement des services par nombre de demandes reçues"></canvas></div>
                    @else
                        <div class="professional-empty-state"><span class="professional-empty-state__icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span><strong>Aucune demande sur cette période</strong><p>Ce classement se remplira lorsque des clients enverront des demandes sur vos services.</p></div>
                    @endif
                </article>
            </div>
        </section>

        <div class="professional-dashboard__lower-grid">
            <section class="professional-dashboard-card" aria-labelledby="recent-activity-title">
                <div class="professional-dashboard-card__header">
                    <div><span class="professional-dashboard-card__eyebrow">HISTORIQUE</span><h2 id="recent-activity-title">Activité récente</h2><p>Derniers événements issus de vos modules métier.</p></div>
                    <a href="{{ route('professional.requests.index') }}">Voir les demandes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                @if ($dashboard['recent_activity']->isEmpty())
                    <div class="professional-empty-state"><span class="professional-empty-state__icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><strong>Votre activité apparaîtra ici</strong><p>Les demandes, commandes et avis publiés seront affichés lorsqu’ils existeront.</p></div>
                @else
                    <ol class="professional-timeline">
                        @foreach ($dashboard['recent_activity'] as $event)
                            <li>
                                <span class="professional-timeline__icon" aria-hidden="true">
                                    @if ($event['type'] === 'request') <i class="fa-solid fa-inbox"></i>
                                    @elseif ($event['type'] === 'review') <i class="fa-solid fa-star"></i>
                                    @elseif ($event['type'] === 'quote') <i class="fa-solid fa-file-invoice-dollar"></i>
                                    @elseif ($event['type'] === 'revenue') <i class="fa-solid fa-coins"></i>
                                    @elseif ($event['type'] === 'withdrawal') <i class="fa-solid fa-money-bill-transfer"></i>
                                    @else <i class="fa-solid fa-list-check"></i> @endif
                                </span>
                                <div class="professional-timeline__content">
                                    <a href="{{ $event['url'] }}"><strong>{{ $event['title'] }}</strong></a>
                                    <p>{{ $event['description'] }}</p>
                                    <time datetime="{{ $event['at']?->toIso8601String() }}">{{ $event['at']?->locale(app()->getLocale())->diffForHumans() ?? 'Date non renseignée' }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section class="professional-dashboard-card" aria-labelledby="upcoming-orders-title">
                <div class="professional-dashboard-card__header">
                    <div><span class="professional-dashboard-card__eyebrow">ORGANISATION</span><h2 id="upcoming-orders-title">Prochaines prestations</h2><p>Dates souhaitées renseignées dans les demandes associées aux commandes actives.</p></div>
                    <a href="{{ route('professional.orders.index') }}">Toutes les commandes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                @if ($dashboard['upcoming_orders']->isEmpty())
                    <div class="professional-empty-state"><span class="professional-empty-state__icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><strong>Aucune date à venir enregistrée</strong><p>Les prestations disposant d’une date future renseignée apparaîtront ici. Aucune échéance n’est inventée.</p></div>
                @else
                    <ul class="professional-upcoming-list">
                        @foreach ($dashboard['upcoming_orders'] as $upcoming)
                            <li>
                                <div class="professional-upcoming-list__date">
                                    <strong>{{ $upcoming['desired_at']?->format('d') }}</strong>
                                    <span>{{ $upcoming['desired_at']?->locale(app()->getLocale())->translatedFormat('M Y') }}</span>
                                </div>
                                <div class="professional-upcoming-list__copy">
                                    <a href="{{ $upcoming['url'] }}"><strong>{{ $upcoming['title'] }}</strong></a>
                                    <span>{{ $upcoming['reference'] }}</span>
                                    <small>{{ $upcoming['status'] === 'confirmed' ? 'Confirmée' : 'En cours' }} · {{ $upcoming['desired_at']?->format('H:i') }}</small>
                                </div>
                                <a class="professional-upcoming-list__open" href="{{ $upcoming['url'] }}" aria-label="Ouvrir {{ $upcoming['reference'] }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="professional-dashboard-card" aria-labelledby="services-summary-title">
            <div class="professional-dashboard-card__header">
                <div><span class="professional-dashboard-card__eyebrow">CATALOGUE</span><h2 id="services-summary-title">État de vos services</h2><p>Un service publié n’est pas la même chose qu’une demande reçue.</p></div>
                <a href="{{ route('professional.services.index') }}">Gérer mes services <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="professional-service-summary">
                <div><span>Total</span><strong>{{ $services['total'] }}</strong></div>
                <div><span>Publiés</span><strong>{{ $services['published'] }}</strong></div>
                <div><span>Brouillons</span><strong>{{ $services['draft'] }}</strong></div>
                <div><span>Non publiés</span><strong>{{ $services['unpublished'] }}</strong></div>
                <div><span>Archivés</span><strong>{{ $services['archived'] }}</strong></div>
                <div><span>Favoris reçus</span><strong>{{ $engagement['received_favorites'] }}</strong></div>
                <div><span>Notifications non lues</span><strong>{{ $notifications['unread'] }}</strong></div>
                <div><span>Vérification</span><strong class="professional-service-summary__text">{{ $verificationLabels[$verification] ?? 'Non renseignée' }}</strong></div>
            </div>
            @if ($services['published'] === 0)
                <div class="professional-dashboard-card__notice professional-dashboard-card__notice--warning">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <p>{{ $services['total'] === 0 ? 'Créez votre premier service pour commencer à présenter votre expertise.' : 'Publiez au moins un service pour apparaître dans le catalogue.' }}</p>
                    <a href="{{ route('professional.services.create') }}">Créer un service</a>
                </div>
            @else
                <div class="professional-dashboard-card__notice professional-dashboard-card__notice--success">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <p>Votre catalogue contient {{ $services['published'] }} service{{ $services['published'] > 1 ? 's' : '' }} publié{{ $services['published'] > 1 ? 's' : '' }}.</p>
                </div>
            @endif
        </section>

        <section class="professional-dashboard__quick-links" aria-label="Accès rapides aux modules professionnels">
            <a href="{{ route('professional.profile') }}"><i class="fa-solid fa-id-card" aria-hidden="true"></i><span>Mon profil</span></a>
            <a href="{{ route('professional.services.index') }}"><i class="fa-solid fa-briefcase" aria-hidden="true"></i><span>Services</span></a>
            <a href="{{ route('professional.requests.index') }}"><i class="fa-solid fa-inbox" aria-hidden="true"></i><span>Demandes</span></a>
            <a href="{{ route('professional.quotes.index') }}"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i><span>Devis</span></a>
            <a href="{{ route('professional.orders.index') }}"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Commandes</span></a>
            <a href="{{ route('professional.reviews') }}"><i class="fa-solid fa-star" aria-hidden="true"></i><span>Avis</span></a>
            <a href="{{ route('professional.revenues.index') }}"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Revenus</span></a>
            <a href="{{ route('professional.wallet') }}"><i class="fa-solid fa-wallet" aria-hidden="true"></i><span>Portefeuille</span></a>
            <a href="{{ route('professional.withdrawals') }}"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i><span>Retraits</span></a>
            <a href="{{ route('professional.messages') }}"><i class="fa-solid fa-comments" aria-hidden="true"></i><span>Messages</span></a>
            <a href="{{ route('professional.notifications') }}"><i class="fa-solid fa-bell" aria-hidden="true"></i><span>Notifications</span></a>
            <a href="{{ route('professional.documents.index') }}"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span>Documents</span></a>
        </section>
    </div>
@endsection
