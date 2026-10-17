@extends('layouts.professional')

@section('title', 'Mon profil professionnel — PROXIWORK')
@section('page_heading', 'Mon profil professionnel')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/professional/profile.css')
    @endunless
@endpush

@section('content')
    @php
        $personalProfile = $professional->user->profile;
        $name = trim(($personalProfile?->first_name ?? '').' '.($personalProfile?->last_name ?? '')) ?: $professional->user->name;
        $verification = $professional->verification_status?->value ?? 'pending';
        $accountStatus = $professional->user->account_status?->value ?? 'unknown';
        $statusClass = match ($verification) {
            'verified' => 'success',
            'rejected' => 'danger',
            'under_review', 'pending' => 'warning',
            default => 'neutral',
        };
        $isPubliclyVisible = $professional->status === \App\Models\ProfessionalProfile::STATUS_ACTIVE
            && $professional->visibility === \App\Models\ProfessionalProfile::VISIBILITY_PUBLIC
            && $verification === 'verified'
            && $professional->published_services_count > 0
            && $accountStatus === 'active';

        $serviceStatusLabels = [
            'draft' => 'Brouillon',
            'published' => 'Publié',
            'unpublished' => 'Dépublié',
            'archived' => 'Archivé',
        ];
        $visibilityReasons = [];
        if ($accountStatus !== 'active') {
            $visibilityReasons[] = ['message' => 'Votre compte n’est pas actif. Cet état ne peut pas être modifié depuis le profil professionnel.', 'url' => null, 'action' => null];
        }
        if ($professional->status !== \\App\\Models\\ProfessionalProfile::STATUS_ACTIVE) {
            $visibilityReasons[] = ['message' => 'Votre profil professionnel n’est pas dans l’état actif requis pour l’annuaire.', 'url' => null, 'action' => null];
        }
        if ($verification !== 'verified') {
            $visibilityReasons[] = [
                'message' => $verification === 'rejected'
                    ? 'La vérification administrative n’est pas approuvée. Consultez votre dossier avant de transmettre d’autres justificatifs.'
                    : 'La vérification administrative doit être approuvée avant l’affichage public.',
                'url' => route('professional.documents.index'),
                'action' => 'Consulter mes documents',
            ];
        }
        if ($professional->visibility !== \\App\\Models\\ProfessionalProfile::VISIBILITY_PUBLIC) {
            $visibilityReasons[] = ['message' => 'Votre préférence de visibilité est réglée sur privé.', 'url' => route('professional.profile.edit'), 'action' => 'Modifier la visibilité'];
        }
        if ($professional->published_services_count === 0) {
            $visibilityReasons[] = ['message' => 'Aucun service admissible n’est actuellement publié.', 'url' => route('professional.services.index'), 'action' => 'Gérer mes services'];
        }
    @endphp

    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Identité professionnelle</p>
            <h1>Votre profil professionnel</h1>
            <p class="page-lead">Gérez votre présentation, vos compétences, votre disponibilité et les informations visibles par les clients.</p>
            <p class="profile-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ now()->translatedFormat('l j F Y') }}</p>
        </div>
        <div class="dashboard-page-header__actions">
            @if ($isPubliclyVisible)
                <a class="button button--secondary" href="{{ route('public.professionals.show', $professional) }}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Voir mon profil public
                </a>
            @endif
            <a class="button button--primary" href="{{ route('professional.profile.edit') }}">
                <i class="fa-solid fa-pen" aria-hidden="true"></i> Modifier le profil
            </a>
        </div>
    </div>

    <section class="professional-profile-hero surface-card surface-card--lg" aria-labelledby="professional-profile-identity">
        <div class="professional-profile-identity">
            @if ($personalProfile?->avatar_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($personalProfile->avatar_path))
                <img class="avatar avatar--xl professional-profile-avatar" src="{{ route('professional.profile.photo') }}" alt="Photo de profil de {{ $name }}">
            @else
                <span class="avatar avatar--xl professional-profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
            @endif
            <div class="professional-profile-identity__content">
                <div class="professional-profile-title-row">
                    <h2 id="professional-profile-identity">{{ $name }}</h2>
                    <span class="badge badge--{{ $statusClass }}">
                        <i class="fa-solid {{ $verification === 'verified' ? 'fa-circle-check' : 'fa-shield-halved' }}" aria-hidden="true"></i>
                        Vérification : {{ $verificationLabels[$verification] ?? 'À confirmer' }}
                    </span>
                </div>
                <p class="professional-title">{{ $professional->professional_title ?: 'Titre professionnel à compléter' }}</p>
                <div class="professional-meta">
                    <span><i class="fa-solid fa-user-check" aria-hidden="true"></i> Compte : {{ $accountStatus === 'active' ? 'Actif' : str_replace('_', ' ', $accountStatus) }}</span>
                    <span><i class="fa-solid fa-id-card" aria-hidden="true"></i> Profil : {{ $profileStatusLabels[$professional->status] ?? $professional->status }}</span>
                    <span><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Disponibilité : {{ $availabilityLabels[$professional->availability_status?->value ?? 'unknown'] ?? 'Non renseignée' }}</span>
                    <span><i class="fa-solid fa-eye" aria-hidden="true"></i> Visibilité : {{ $visibilityLabels[$professional->visibility] ?? $professional->visibility }}</span>
                    @if ($professional->city || $professional->province)
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</span>
                    @endif
                    @if ($professional->years_experience !== null)
                        <span><i class="fa-solid fa-briefcase" aria-hidden="true"></i> {{ $professional->years_experience }} an(s) d’expérience</span>
                    @endif
                    <span><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Inscrit le {{ $professional->user->created_at?->translatedFormat('j F Y') ?? 'date non disponible' }}</span>
                </div>
            </div>
        </div>
        @if (! $isPubliclyVisible)
            <div class="profile-visibility-notice profile-visibility-notice--blocked" role="status" aria-labelledby="profile-visibility-title">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div>
                    <strong id="profile-visibility-title">Votre profil n’est pas actuellement visible dans l’annuaire public.</strong>
                    <p>Les conditions non remplies sont indiquées ci-dessous. La complétude du profil ne remplace pas la vérification administrative.</p>
                    <ul class="profile-visibility-reasons">
                        @foreach ($visibilityReasons as $reason)
                            <li>
                                <span>{{ $reason['message'] }}</span>
                                @if ($reason['url'])
                                    <a href="{{ $reason['url'] }}">{{ $reason['action'] }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </section>

        @if ($professional->published_services_count === 0)
            <div class="profile-visibility-notice profile-service-notice" role="status">
                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                <div>
                    <strong>Aucun service publié</strong>
                    <p>La complétude de votre profil est indépendante du catalogue. Pour apparaître dans l’annuaire, gérez vos offres et publiez au moins un service admissible.</p>
                    <a class="button button--secondary button--sm" href="{{ route('professional.services.index') }}">Gérer mes services</a>
                </div>
            </div>
        @endif

    <div class="professional-profile-grid">
        <section class="surface-card surface-card--md" aria-labelledby="profile-about-heading">
            <div class="surface-card__header"><h2 id="profile-about-heading">Présentation professionnelle</h2></div>
            <div class="surface-card__body">
                @if (filled($professional->description))
                    <p class="profile-bio">{{ $professional->description }}</p>
                @else
                    <div class="state-empty state-empty--compact">
                        <div>
                            <span class="state-empty__icon"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></span>
                            <h3>Présentation à compléter</h3>
                            <p>Expliquez votre spécialité, les types de travaux que vous réalisez et votre expérience réelle.</p>
                            <a class="button button--secondary" href="{{ route('professional.profile.edit') }}#professional-description">Ajouter une présentation</a>
                        </div>
                    </div>
                @endif
                @if ($professional->starting_price !== null)
                    <p class="profile-price"><span>Prix indicatif de départ</span><strong>{{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}</strong></p>
                    <p class="field-help">Ce prix est indicatif ; les prix contractuels restent définis par vos services et devis.</p>
                @endif
            </div>
        </section>

        <section class="surface-card surface-card--md" aria-labelledby="profile-completion-heading">
            <div class="surface-card__header"><h2 id="profile-completion-heading">Complétude du profil</h2></div>
            <div class="surface-card__body">
                <div class="completion-score"><strong>{{ $completionPercentage }}%</strong><span>des éléments essentiels renseignés</span></div>
                <div class="completion-track" role="progressbar" aria-label="Complétude du profil" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completionPercentage }}"><span style="width:{{ $completionPercentage }}%"></span></div>
                <p class="field-help">La complétude mesure les informations renseignées. Elle ne signifie pas que le compte ou les documents sont vérifiés.</p>
                <ul class="completion-list">
                    <li class="{{ $completionItems['personal'] ? 'is-complete' : '' }}"><i class="fa-solid {{ $completionItems['personal'] ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i> Prénom et nom</li>
                    <li class="{{ $completionItems['profession'] ? 'is-complete' : '' }}"><i class="fa-solid {{ $completionItems['profession'] ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i> Titre professionnel</li>
                    <li class="{{ $completionItems['presentation'] ? 'is-complete' : '' }}"><i class="fa-solid {{ $completionItems['presentation'] ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i> Présentation</li>
                    <li class="{{ $completionItems['service_area'] ? 'is-complete' : '' }}"><i class="fa-solid {{ $completionItems['service_area'] ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i> Ville et province</li>
                    <li class="{{ $completionItems['skills'] ? 'is-complete' : '' }}"><i class="fa-solid {{ $completionItems['skills'] ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i> Au moins une compétence</li>
                        </ul>
                @if (! $completionItems['personal'] || ! $completionItems['profession'] || ! $completionItems['presentation'] || ! $completionItems['service_area'] || ! $completionItems['skills'])
                    <a class="button button--secondary profile-completion-action" href="{{ route('professional.profile.edit') }}">Compléter les informations manquantes</a>
                @endif
            </div>
        </section>
    </div>

    <section class="surface-card surface-card--md professional-section" aria-labelledby="profile-languages-heading">
        <div class="surface-card__header professional-section-header"><div><h2 id="profile-languages-heading">Langues de communication</h2><p>Les langues sont déclarées par le professionnel et ne font pas l’objet d’une vérification automatique.</p></div><a class="button button--ghost button--sm" href="{{ route('professional.profile.edit') }}">Modifier</a></div>
        <div class="surface-card__body">
            @if (! empty($professional->languages))
                <div class="skill-list">
                    @foreach ($professional->languages as $language)
                        <span class="badge badge--neutral">{{ $language }}</span>
                    @endforeach
                </div>
            @else
                <p class="field-help">Aucune langue renseignée pour le moment.</p>
            @endif
        </div>
    </section>

    <section class="surface-card surface-card--md professional-section" aria-labelledby="profile-skills-heading">
        <div class="surface-card__header professional-section-header">
            <div><h2 id="profile-skills-heading">Compétences</h2><p>Les compétences décrivent votre expertise ; elles ne créent pas automatiquement une offre commerciale.</p></div>
            <a class="button button--ghost button--sm" href="{{ route('professional.profile.edit') }}#professional-skills">Gérer les compétences</a>
        </div>
        <div class="surface-card__body">
            @if ($professional->skills->isNotEmpty())
                <div class="skill-list">
                    @foreach ($professional->skills as $skill)
                        <article class="skill-item">
                            <div class="skill-item__top"><strong>{{ $skill->name }}</strong><span>{{ $skill->pivot->proficiency_level ? ucfirst($skill->pivot->proficiency_level) : 'Niveau non renseigné' }}</span></div>
                            @if ($skill->pivot->years_experience !== null)
                                <small>{{ $skill->pivot->years_experience }} an(s) d’expérience déclarée</small>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="state-empty state-empty--compact"><div><span class="state-empty__icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><h3>Aucune compétence associée</h3><p>Sélectionnez les compétences réellement maîtrisées dans le catalogue disponible.</p><a class="button button--primary" href="{{ route('professional.profile.edit') }}#professional-skills">Ajouter des compétences</a></div></div>
            @endif
        </div>
    </section>

    <section class="surface-card surface-card--md professional-section" aria-labelledby="profile-services-heading">
        <div class="surface-card__header professional-section-header">
            <div><h2 id="profile-services-heading">Services</h2><p>Le catalogue reste géré dans son propre module.</p></div>
            <a class="button button--ghost button--sm" href="{{ route('professional.services.index') }}">Gérer les services</a>
        </div>
        <div class="surface-card__body">
            @if ($professional->services->isNotEmpty())
                <div class="service-mini-grid">
                    @foreach ($professional->services as $service)
                        <article class="service-mini-card">
                            <div class="service-mini-card__head"><span class="service-mini-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><span class="badge badge--{{ $service->status->value === 'published' ? 'success' : ($service->status->value === 'draft' ? 'warning' : 'neutral') }}">{{ $serviceStatusLabels[$service->status->value] ?? 'Statut à confirmer' }}</span></div>
                            <h3>{{ $service->title }}</h3>
                            <p>{{ $service->short_description ?: 'Aucune description courte.' }}</p>
                            <small class="service-mini-card__mode"><i class="fa-solid fa-laptop-house" aria-hidden="true"></i> {{ $service->execution_mode?->label() ?? 'Mode non renseigné' }}</small>
                            <a href="{{ route('professional.services.edit', $service) }}">Gérer <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="state-empty state-empty--compact"><div><span class="state-empty__icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><h3>Aucun service</h3><p>Créez une offre depuis le catalogue des services pour commencer à présenter vos prestations.</p><a class="button button--primary" href="{{ route('professional.services.create') }}">Créer un service</a></div></div>
            @endif
        </div>
    </section>

    <section class="surface-card surface-card--md professional-section" aria-labelledby="profile-verification-heading">
        <div class="surface-card__header professional-section-header"><div><h2 id="profile-verification-heading">Vérification et documents</h2><p>La vérification est gérée par le processus administratif, pas par ce formulaire.</p></div><a class="button button--secondary button--sm" href="{{ route('professional.documents.index') }}">Gérer mes documents</a></div>
        <div class="surface-card__body">
            <p><strong>État actuel :</strong> {{ $verificationLabels[$verification] ?? 'À confirmer' }}</p>
            @if ($verification !== 'verified')
                <p class="field-help">Transmettez les justificatifs demandés via le module Documents. L’envoi d’un document ne signifie pas qu’il a été vérifié.</p>
            @else
                <p class="field-help">Votre statut est marqué comme vérifié par le système. Ne modifiez pas cet état depuis le profil.</p>
            @endif
        </div>
    </section>
@endsection
