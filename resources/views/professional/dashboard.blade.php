@extends('layouts.professional')

@section('title', 'Tableau de bord professionnel — PROXIWORK')
@section('meta_description', 'Pilotez votre activité professionnelle, vos services et votre engagement sur PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/professional/dashboard.css')
    @endunless
@endpush

@section('page_eyebrow', 'VUE D’ENSEMBLE')
@section('page_title', 'Tableau de bord')
@section('page_description', 'Suivez votre activité professionnelle et les éléments qui nécessitent votre attention.')

@php
    $profile = $dashboard['profile'];
    $services = $dashboard['services'];
    $engagement = $dashboard['engagement'];
    $notifications = $dashboard['notifications'];
    $pendingActions = $dashboard['pending_actions'];

    $verificationLabels = [
        'pending' => 'En attente',
        'under_review' => 'En cours de vérification',
        'needs_information' => 'Informations complémentaires requises',
        'verified' => 'Vérifié',
        'rejected' => 'Rejeté',
    ];

    $availabilityLabels = [
        'unknown' => 'Non renseignée',
        'available' => 'Disponible',
        'unavailable' => 'Indisponible',
    ];

    $verification = $profile['verification_status'] ?? 'pending';
    $availability = $profile['availability_status'] ?? 'unknown';
    $displayName = $profile['user']['name'] ?? 'Professionnel';
@endphp

@section('page_actions')
    <a class="button button--primary" href="{{ url('/professional/services') }}">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span>Créer un service</span>
    </a>
@endsection

@section('content')
    <div class="professional-dashboard" data-professional-dashboard>
        <section class="professional-dashboard__welcome" aria-labelledby="professional-dashboard-welcome-title">
            <div class="professional-dashboard__welcome-icon" aria-hidden="true">
                <i class="fa-solid fa-briefcase"></i>
            </div>

            <div>
                <span class="professional-dashboard__eyebrow">ESPACE PROFESSIONNEL</span>
                <h3 id="professional-dashboard-welcome-title">Bonjour {{ $displayName }} 👋</h3>
                <p>
                    {{ $profile['professional_title'] ?: 'Présentez vos services et développez votre activité sur PROXIWORK.' }}
                </p>
            </div>
        </section>

        @if (in_array($verification, ['pending', 'under_review', 'needs_information', 'rejected'], true))
            <section class="professional-dashboard-card professional-dashboard-card--verification" aria-labelledby="verification-guidance-title">
                <div class="professional-dashboard-card__header">
                    <div>
                        <span class="professional-dashboard-card__eyebrow">VÉRIFICATION DU DOSSIER</span>
                        <h3 id="verification-guidance-title">{{ $verificationLabels[$verification] ?? 'Statut du dossier' }}</h3>
                    </div>
                </div>
                @if ($verification === 'needs_information')
                    <div class="professional-dashboard-card__notice professional-dashboard-card__notice--warning">
                        <i class="fa-solid fa-clipboard-question" aria-hidden="true"></i>
                        <p>{{ $profile['verification_note'] ?: 'L’administration demande des informations ou des corrections complémentaires. Consultez les indications et mettez votre dossier à jour.' }}</p>
                    </div>
                    <form method="POST" action="{{ route('professional.documents.store') }}" enctype="multipart/form-data" class="professional-verification-upload">
                        @csrf
                        <h4>Ajouter un justificatif</h4>
                        <label for="professional-document-type">Type de document</label>
                        <select id="professional-document-type" name="type" required>
                            <option value="identity">Pièce d’identité</option>
                            <option value="certificate">Certificat</option>
                            <option value="diploma">Diplôme</option>
                            <option value="license">Licence / autorisation</option>
                            <option value="reference">Référence / expérience</option>
                            <option value="other">Autre</option>
                        </select>
                        <label for="professional-document-file">Fichier (PDF ou image, 5 Mo maximum)</label>
                        <input id="professional-document-file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required>
                        <button class="button button--secondary" type="submit">Ajouter au dossier privé</button>
                    </form>
                    <form method="POST" action="{{ route('professional.verification.resubmit') }}" class="professional-verification-resubmit">
                        @csrf
                        <label><input type="checkbox" name="confirm" value="1" required> J’ai corrigé les éléments demandés et je souhaite soumettre à nouveau mon dossier.</label>
                        <button class="button button--primary" type="submit">Soumettre à nouveau</button>
                    </form>
                @elseif ($verification === 'rejected')
                    <div class="professional-dashboard-card__notice professional-dashboard-card__notice--warning">
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                        <p>{{ $profile['verification_note'] ?: 'La vérification de votre dossier a été rejetée. Consultez le motif communiqué par l’administration et les possibilités de correction qui vous ont été indiquées.' }}</p>
                    </div>
                @else
                    <div class="professional-dashboard-card__notice professional-dashboard-card__notice--warning">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <p>{{ $verification === 'pending' ? 'Votre dossier a été reçu et attend la revue de l’administration.' : 'Votre dossier est en cours de vérification. Vous pouvez continuer à compléter votre profil, mais votre visibilité publique reste désactivée.' }}</p>
                    </div>
                @endif
            </section>
        @endif

        <section class="professional-dashboard__stats" aria-label="Statistiques principales">
            <article class="professional-stat-card">
                <div class="professional-stat-card__icon professional-stat-card__icon--primary" aria-hidden="true">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
                <div>
                    <span>Services</span>
                    <strong>{{ $services['total'] }}</strong>
                    <small>{{ $services['published'] }} publié{{ $services['published'] > 1 ? 's' : '' }}</small>
                </div>
            </article>

            <article class="professional-stat-card">
                <div class="professional-stat-card__icon professional-stat-card__icon--success" aria-hidden="true">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <span>Services publiés</span>
                    <strong>{{ $services['published'] }}</strong>
                    <small>{{ $services['draft'] }} brouillon{{ $services['draft'] > 1 ? 's' : '' }}</small>
                </div>
            </article>

            <article class="professional-stat-card">
                <div class="professional-stat-card__icon professional-stat-card__icon--accent" aria-hidden="true">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <div>
                    <span>Favoris reçus</span>
                    <strong>{{ $engagement['received_favorites'] }}</strong>
                    <small>Professionnels suivis par des clients</small>
                </div>
            </article>

            <article class="professional-stat-card">
                <div class="professional-stat-card__icon professional-stat-card__icon--info" aria-hidden="true">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div>
                    <span>Évaluation</span>
                    <strong>{{ number_format((float) ($engagement['rating_average'] ?? 0), 1, ',', ' ') }} <span aria-hidden="true">★</span></strong>
                    <small>{{ $engagement['rating_count'] }} avis</small>
                </div>
            </article>
        </section>

        <div class="professional-dashboard__grid">
            <section class="professional-dashboard-card professional-dashboard-card--services" aria-labelledby="services-summary-title">
                <div class="professional-dashboard-card__header">
                    <div>
                        <span class="professional-dashboard-card__eyebrow">CATALOGUE</span>
                        <h3 id="services-summary-title">Mes services</h3>
                    </div>
                    <a href="{{ url('/professional/services') }}">Gérer <span class="sr-only">mes services</span> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>

                <div class="professional-service-summary">
                    <div>
                        <span>Publiés</span>
                        <strong>{{ $services['published'] }}</strong>
                    </div>
                    <div>
                        <span>Brouillons</span>
                        <strong>{{ $services['draft'] }}</strong>
                    </div>
                    <div>
                        <span>Non publiés</span>
                        <strong>{{ $services['unpublished'] }}</strong>
                    </div>
                    <div>
                        <span>Archivés</span>
                        <strong>{{ $services['archived'] }}</strong>
                    </div>
                </div>

                @if ($verification !== 'verified')
                    <div class="professional-dashboard-card__notice professional-dashboard-card__notice--warning">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <p>Les services restent en brouillon ou non publics tant que la vérification professionnelle n’est pas approuvée.</p>
                    </div>
                @elseif ($services['published'] === 0)
                    <div class="professional-dashboard-card__notice professional-dashboard-card__notice--warning">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <p>
                            {{ $services['total'] === 0
                                ? 'Créez votre premier service pour commencer à présenter votre expertise.'
                                : 'Préparez un service et publiez-le lorsque toutes les conditions de publication sont satisfaites.' }}
                        </p>
                    </div>
                @else
                    <div class="professional-dashboard-card__notice professional-dashboard-card__notice--success">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <p>Votre catalogue contient {{ $services['published'] }} service{{ $services['published'] > 1 ? 's' : '' }} actuellement publié{{ $services['published'] > 1 ? 's' : '' }}.</p>
                    </div>
                @endif
            </section>

            <section class="professional-dashboard-card" aria-labelledby="actions-title">
                <div class="professional-dashboard-card__header">
                    <div>
                        <span class="professional-dashboard-card__eyebrow">À FAIRE</span>
                        <h3 id="actions-title">Actions à effectuer</h3>
                    </div>
                    @if ($notifications['unread'] > 0)
                        <span class="professional-dashboard-badge">{{ $notifications['unread'] }} non lue{{ $notifications['unread'] > 1 ? 's' : '' }}</span>
                    @endif
                </div>

                @if ($pendingActions->isEmpty())
                    <div class="professional-empty-state">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <strong>Tout est à jour</strong>
                        <p>Aucune action prioritaire ne nécessite votre attention.</p>
                    </div>
                @else
                    <ul class="professional-action-list">
                        @foreach ($pendingActions as $action)
                            <li>
                                <span class="professional-action-list__icon" aria-hidden="true">
                                    @if ($action['type'] === 'notifications')
                                        <i class="fa-solid fa-bell"></i>
                                    @elseif ($action['type'] === 'verification')
                                        <i class="fa-solid fa-shield-halved"></i>
                                    @else
                                        <i class="fa-solid fa-briefcase"></i>
                                    @endif
                                </span>
                                <div>
                                    <strong>{{ $action['type'] === 'notifications' ? 'Notifications' : ($action['type'] === 'verification' ? 'Vérification' : 'Services') }}</strong>
                                    <p>{{ $action['message'] }}</p>
                                </div>
                                <a
                                    class="professional-action-list__link"
                                    href="{{ $action['type'] === 'notifications' ? url('/professional/notifications') : ($action['type'] === 'verification' ? url('/professional/profile') : url('/professional/services')) }}"
                                    aria-label="{{ $action['message'] }}"
                                >
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="professional-dashboard__grid">
            <section class="professional-dashboard-card" aria-labelledby="profile-status-title">
                <div class="professional-dashboard-card__header">
                    <div>
                        <span class="professional-dashboard-card__eyebrow">IDENTITÉ</span>
                        <h3 id="profile-status-title">Statut professionnel</h3>
                    </div>
                    <a href="{{ url('/professional/profile') }}">Mon profil <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>

                <div class="professional-status-list">
                    <div class="professional-status-row">
                        <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Vérification</span>
                        <strong class="professional-status professional-status--{{ $verification }}">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i>
                            {{ $verificationLabels[$verification] ?? 'Statut inconnu' }}
                        </strong>
                    </div>
                    <div class="professional-status-row">
                        <span><i class="fa-solid fa-clock" aria-hidden="true"></i> Disponibilité</span>
                        <strong class="professional-status professional-status--{{ $availability }}">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i>
                            {{ $availabilityLabels[$availability] ?? 'Non renseignée' }}
                        </strong>
                    </div>
                    <div class="professional-status-row">
                        <span><i class="fa-solid fa-bell" aria-hidden="true"></i> Notifications</span>
                        <strong>{{ $notifications['unread'] }} non lue{{ $notifications['unread'] > 1 ? 's' : '' }}</strong>
                    </div>
                </div>
            </section>

            <section class="professional-dashboard-card" aria-labelledby="quick-actions-title">
                <div class="professional-dashboard-card__header">
                    <div>
                        <span class="professional-dashboard-card__eyebrow">RACCOURCIS</span>
                        <h3 id="quick-actions-title">Accès rapides</h3>
                    </div>
                </div>

                <div class="professional-quick-actions">
                    <a href="{{ url('/professional/profile') }}">
                        <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                        <span>Profil</span>
                    </a>
                    <a href="{{ url('/professional/services') }}">
                        <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                        <span>Services</span>
                    </a>
                    <a href="{{ url('/professional/requests') }}">
                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                        <span>Demandes</span>
                    </a>
                    <a href="{{ url('/professional/messages') }}">
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        <span>Messages</span>
                    </a>
                    <a href="{{ url('/professional/notifications') }}">
                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="{{ url('/professional/wallet') }}">
                        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                        <span>Wallet</span>
                    </a>
                </div>
            </section>
        </div>
    </div>
@endsection
