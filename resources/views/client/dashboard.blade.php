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
    <a class="button button--primary" href="{{ route('client.professionals.index') }}">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span>Rechercher un professionnel</span>
    </a>
@endsection

@section('content')
    <div class="client-dashboard" data-client-dashboard>
        <section class="client-dashboard__welcome" aria-labelledby="client-welcome-title">
            <div>
                @if ($dashboard['profile']['photo_url'])
                    <img class="client-dashboard__welcome-photo" src="{{ $dashboard['profile']['photo_url'] }}" alt="Photo de {{ $dashboard['profile']['user']['name'] }}">
                @else
                <span class="client-dashboard__welcome-icon" aria-hidden="true">
                    <i class="fa-solid fa-hand-wave"></i>
                </span>
                @endif
                <div>
                    <p class="client-dashboard__kicker">Bienvenue dans votre espace</p>
                    <h3 id="client-welcome-title">Bonjour, {{ $dashboard['profile']['first_name'] ?: $dashboard['profile']['user']['name'] }} 👋</h3>
                    <p>Nous vous aidons à trouver les bons professionnels pour vos projets.</p>
                    <p class="client-dashboard__date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ now()->locale('fr')->translatedFormat('l j F Y') }}</p>
                </div>
            </div>
            <div class="client-dashboard__welcome-actions">
                <a class="button button--secondary" href="{{ route('client.profile') }}"><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Mon profil</a>
                <a class="button button--secondary" href="{{ route('client.notifications.index') }}"><i class="fa-solid fa-bell" aria-hidden="true"></i> Notifications @if($dashboard['notifications']['unread'] > 0)<span class="client-dashboard__notification-count">{{ $dashboard['notifications']['unread'] }}</span>@endif</a>
                <a class="button button--primary" href="{{ route('client.search') }}"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher un service</a>
            </div>
        </section>

        <section class="client-dashboard__stats" aria-labelledby="client-stats-title">
            <div class="client-dashboard__section-heading">
                <div>
                    <span class="eyebrow">VUE D’ENSEMBLE</span>
                    <h3 id="client-stats-title">Votre espace en chiffres</h3>
                    <p>Consultez rapidement les éléments qui demandent votre attention.</p>
                </div>
            </div>

            <div class="client-dashboard__stat-grid">
                <a class="client-dashboard__stat-card" href="{{ route('client.requests.index', ['bucket' => 'active']) }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                    <span><strong>{{ $dashboard['stats']['requests'] }}</strong><small>Demandes en cours</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <a class="client-dashboard__stat-card" href="{{ route('client.quotes.index', ['bucket' => 'review']) }}">
                    <span class="client-dashboard__stat-icon client-dashboard__stat-icon--warning" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <span><strong>{{ $dashboard['stats']['quotes'] }}</strong><small>Devis à examiner</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <a class="client-dashboard__stat-card" href="{{ route('client.orders.index', ['bucket' => 'active']) }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-cart-shopping"></i></span>
                    <span><strong>{{ $dashboard['stats']['orders'] }}</strong><small>Commandes actives</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <a class="client-dashboard__stat-card" href="{{ route('client.orders.index', ['bucket' => 'pending_payment']) }}">
                    <span class="client-dashboard__stat-icon client-dashboard__stat-icon--warning" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></span>
                    <span><strong>{{ $dashboard['stats']['payments'] }}</strong><small>Paiements à effectuer</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <a class="client-dashboard__stat-card" href="{{ route('client.messages.index', ['status' => 'unread']) }}">
                    <span class="client-dashboard__stat-icon" aria-hidden="true"><i class="fa-solid fa-comments"></i></span>
                    <span><strong>{{ $dashboard['stats']['messages'] }}</strong><small>Conversations avec messages non lus</small></span>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

            </div>
        </section>

        <section class="client-dashboard__actions" aria-labelledby="pending-actions-title">
            <div class="client-dashboard__section-heading">
                <div>
                    <span class="eyebrow">À FAIRE</span>
                    <h3 id="pending-actions-title">Les prochaines actions</h3>
                    <p>Les devis, paiements, livraisons et messages qui demandent votre attention.</p>
                </div>
                @if (count($dashboard['pending_actions']) > 0)
                    <span class="client-dashboard__count-badge" aria-label="{{ count($dashboard['pending_actions']) }} actions affichées">{{ count($dashboard['pending_actions']) }}</span>
                @endif
            </div>

            @if (count($dashboard['pending_actions']) > 0)
                <div class="client-dashboard__action-list">
                    @foreach ($dashboard['pending_actions'] as $action)
                        @php($actionIcons = ['quote' => 'fa-file-invoice-dollar', 'payment' => 'fa-credit-card', 'delivery' => 'fa-box-open', 'message' => 'fa-comments'])
                        <a class="client-dashboard__action{{ in_array($action['type'], ['quote', 'payment', 'delivery'], true) ? ' client-dashboard__action--priority' : '' }}" href="{{ $action['url'] }}">
                            <span class="client-dashboard__action-icon" aria-hidden="true"><i class="fa-solid {{ $actionIcons[$action['type']] ?? 'fa-arrow-right' }}"></i></span>
                            <span class="client-dashboard__action-copy"><strong>{{ $action['title'] }}</strong><small>{{ $action['description'] }}</small></span>
                            <span class="client-dashboard__action-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="client-dashboard__all-clear"><span aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><p>Tout est à jour. Aucune action ne demande votre attention pour le moment.</p></div>
            @endif
        </section>

        <div class="client-dashboard__insight-grid">
            <section class="client-dashboard__card" aria-labelledby="client-chart-title">
                <div class="client-dashboard__card-header"><div><span class="eyebrow">SUIVI</span><h3 id="client-chart-title">Activité des six derniers mois</h3><p>Comparez le nombre de demandes et de commandes créées chaque mois.</p></div><span class="client-dashboard__card-icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span></div>
                <div class="client-dashboard__chart" role="list" aria-label="Nombre de demandes et de commandes pour chacun des six derniers mois">
                    @foreach($dashboard['chart'] as $month)
                        <div class="client-dashboard__chart-row" role="listitem">
                            <span class="client-dashboard__chart-month">{{ $month['label'] }}</span>
                            <div class="client-dashboard__chart-metrics">
                                <div class="client-dashboard__chart-metric">
                                    <span class="client-dashboard__chart-metric-label">Demandes <strong>{{ $month['requests'] }}</strong></span>
                            <span class="client-dashboard__chart-track" aria-hidden="true"><span class="client-dashboard__chart-bar client-dashboard__chart-bar--requests" style="width: {{ $month['requests_width'] }}%"></span></span>
                                </div>
                                <div class="client-dashboard__chart-metric">
                                    <span class="client-dashboard__chart-metric-label">Commandes <strong>{{ $month['orders'] }}</strong></span>
                            <span class="client-dashboard__chart-track" aria-hidden="true"><span class="client-dashboard__chart-bar client-dashboard__chart-bar--orders" style="width: {{ $month['orders_width'] }}%"></span></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="client-dashboard__chart-legend"><span><i></i> Demandes</span><span><i></i> Commandes</span></p>
            </section>

            <section class="client-dashboard__card" aria-labelledby="client-activity-title">
                <div class="client-dashboard__card-header"><div><span class="eyebrow">HISTORIQUE</span><h3 id="client-activity-title">Activité récente</h3>
                    <p>Jusqu’à huit événements récents, toutes rubriques confondues.</p></div></div>
                @php($activityStatusLabels = [
                    'draft' => 'Brouillon',
                    'requested' => 'En attente du professionnel',
                    'quoted' => 'Devis à consulter',
                    'accepted' => 'Demande acceptée',
                    'rejected' => 'Demande refusée',
                    'cancelled' => 'Annulée',
                    'pending_payment' => 'Paiement en attente',
                    'confirmed' => 'Confirmée',
                    'in_progress' => 'En cours',
                    'delivery_submitted' => 'Livraison envoyée',
                    'completed' => 'Terminée',
                    'disputed' => 'En litige',
                    'sent' => 'Envoyé au client',
                    'negotiating' => 'En négociation',
                    'expired' => 'Expiré',
                    'withdrawn' => 'Retiré',
                    'succeeded' => 'Confirmé',
                    'failed' => 'Échec enregistré',
                    'cancelled_payment' => 'Annulé',
                    'refunded' => 'Remboursé',
                    'recent' => 'Message récent',
                ])
                @if(count($dashboard['recent_activity']) > 0)
                    <div class="client-dashboard__activity-list">@foreach($dashboard['recent_activity'] as $activity)<a href="{{ $activity['url'] }}"><span class="client-dashboard__activity-icon"><i class="fa-solid {{ ['order' => 'fa-cart-shopping', 'quote' => 'fa-file-invoice-dollar', 'payment' => 'fa-credit-card', 'message' => 'fa-comments'][$activity['type']] ?? 'fa-file-lines' }}" aria-hidden="true"></i></span><span><strong>{{ $activity['label'] }} · {{ $activity['title'] }}</strong><small>{{ $activity['detail'] ?: ($activityStatusLabels[$activity['status']] ?? 'Mise à jour') }}@if($activity['occurred_at']) · {{ \Illuminate\Support\Carbon::parse($activity['occurred_at'])->locale('fr')->diffForHumans() }}@endif</small></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endforeach</div>
                @else
                    <div class="client-dashboard__empty client-dashboard__empty--activity">
                        <span class="client-dashboard__empty-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <h4>Aucune activité récente</h4>
                        <p>Votre activité apparaîtra ici au fil de vos demandes et de vos commandes.</p>
                    </div>
                @endif
            </section>
        </div>

        <div class="client-dashboard__grid">
            <section class="client-dashboard__card" aria-labelledby="default-address-title">
                <div class="client-dashboard__card-header">
                    <div>
                        <span class="eyebrow">ADRESSE</span>
                        <h3 id="default-address-title">Adresse principale</h3>
                        <p>Le lieu proposé pour vos prochaines demandes.</p>
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
                        <p>{{ collect([$address['address_line_1'], $address['neighborhood'], $address['commune']])->filter()->join(', ') }}</p>
                        <p>{{ collect([$address['city'], $address['province']])->filter()->join(', ') }}</p>
                    </div>
                    <a class="text-link" href="{{ route('client.addresses.index') }}">
                        Gérer mes adresses <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                @else
                    <div class="client-dashboard__empty">
                        <span class="client-dashboard__empty-icon" aria-hidden="true">
                            <i class="fa-solid fa-location-dot"></i>
                        </span>
                        <h4>Aucune adresse principale</h4>
                        <p>Ajoutez une adresse pour faciliter vos prochaines demandes.</p>
                        <a class="button button--secondary" href="{{ route('client.addresses.index') }}">
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
                        <p>Gardez vos coordonnées personnelles à jour.</p>
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

                <a class="text-link" href="{{ route('client.profile') }}">
                    Gérer mon profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </section>
        </div>

        <section class="client-dashboard__discovery" aria-labelledby="client-discovery-title">
            <header class="client-dashboard__section-heading">
                <div>
                    <span class="eyebrow">POURSUIVRE VOTRE PARCOURS</span>
                    <h3 id="client-discovery-title">Des professionnels et services à découvrir</h3>
                    <p>Des profils admissibles et des prestations publiées, choisis parmi les données disponibles.</p>
                </div>
                <a class="button button--ghost" href="{{ route('client.search') }}">Rechercher un service <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>

            @if ($dashboard['professionals']->isNotEmpty())
                <div class="client-dashboard__discovery-grid">
                    @foreach ($dashboard['professionals'] as $professional)
                        @php
                            $professionalProfile = $professional->user?->profile;
                            $professionalName = $professional->business_name
                                ?: trim(($professionalProfile?->first_name ?? '').' '.($professionalProfile?->last_name ?? ''))
                                ?: $professional->user?->name
                                ?: 'Professionnel PROXIWORK';
                            $hasProfessionalPhoto = $professionalProfile?->avatar_path
                                && \Illuminate\Support\Facades\Storage::disk('local')->exists($professionalProfile->avatar_path);
                        @endphp
                        <article class="client-dashboard__professional-card">
                            <div class="client-dashboard__professional-identity">
                                @if ($professional->user?->profile?->avatar_path && \\Illuminate\\Support\\Facades\\Storage::disk('local')->exists($professional->user->profile->avatar_path))
                                    <img src="{{ route('client.professionals.photo', $professional) }}" alt="Photo de {{ $professionalName }}" loading="lazy">
                                @else
                                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr($professionalName, 0, 1)) }}</span>
                                @endif
                                <div><h4>{{ $professionalName }}</h4><p>{{ $professional->professional_title ?: 'Professionnel vérifié' }}</p></div>
                            </div>
                            <div class="client-dashboard__professional-meta">
                                <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Profil vérifié</span>
                                @if ($professional->city || $professional->province)
                                    <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</span>
                                @endif
                                @if ($professional->rating_count > 0)
                                    <span><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5 · {{ $professional->rating_count }} avis</span>
                                @endif
                            </div>
                            <a class="button button--secondary" href="{{ route('client.professionals.show', $professional) }}">Voir le profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="client-dashboard__discovery-empty"><i class="fa-solid fa-compass" aria-hidden="true"></i><p>Aucun profil admissible n’est disponible pour le moment.</p><a class="button button--secondary" href="{{ route('client.professionals.index') }}">Ouvrir l’annuaire</a></div>
            @endif

            @if ($dashboard['services']->isNotEmpty())
                <div class="client-dashboard__service-grid">
                    @foreach ($dashboard['services'] as $service)
                        @php($cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first())
                        <article class="client-dashboard__service-card">
                            <a class="client-dashboard__service-media" href="{{ route('public.services.show', $service) }}">
                                @if ($cover)
                                    <img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" loading="lazy">
                                @else
                                    <span aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                                @endif
                            </a>
                            <div class="client-dashboard__service-body">
                                <span class="eyebrow">{{ $service->category?->name ?: 'Service publié' }}</span>
                                <h4><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h4>
                                <p>{{ $service->professionalProfile?->business_name ?: $service->professionalProfile?->user?->name }}</p>
                                <strong>
                                    @switch($service->pricing_type?->value)
                                        @case('fixed') Prix fixe · {{ number_format((float) $service->price, 2, ',', ' ') }} {{ $service->currency }} @break
                                        @case('from') À partir de {{ number_format((float) $service->price, 2, ',', ' ') }} {{ $service->currency }} @break
                                        @case('range') De {{ number_format((float) $service->price_min, 2, ',', ' ') }} à {{ number_format((float) $service->price_max, 2, ',', ' ') }} {{ $service->currency }} @break
                                        @default Sur devis
                                    @endswitch
                                </strong>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="client-dashboard__favorites" aria-labelledby="client-favorites-title">
            <header class="client-dashboard__section-heading">
                <div><span class="eyebrow">VOS REPÈRES</span><h3 id="client-favorites-title">Favoris récents</h3><p>{{ $dashboard['favorites']['count'] }} favori{{ $dashboard['favorites']['count'] > 1 ? 's' : '' }} enregistré{{ $dashboard['favorites']['count'] > 1 ? 's' : '' }}.</p></div>
                <a class="button button--ghost" href="{{ route('client.favorites.index') }}">Gérer mes favoris <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            @if ($dashboard['favorites']['recent'] !== [])
                <div class="client-dashboard__favorite-list">
                    @foreach ($dashboard['favorites']['recent'] as $favorite)
                        <a href="{{ $favorite['url'] }}"><span class="client-dashboard__favorite-icon" aria-hidden="true"><i class="fa-solid {{ $favorite['type'] === 'professional' ? 'fa-user-tie' : 'fa-briefcase' }}"></i></span><span><strong>{{ $favorite['title'] }}</strong><small>{{ $favorite['detail'] }}</small></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    @endforeach
                </div>
            @else
                <div class="client-dashboard__discovery-empty"><i class="fa-regular fa-heart" aria-hidden="true"></i><p>Vos professionnels et services favoris apparaîtront ici.</p><a class="button button--secondary" href="{{ route('client.professionals.index') }}">Découvrir les professionnels</a></div>
            @endif
        </section>
    </div>
@endsection
