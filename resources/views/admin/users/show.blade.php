@extends('layouts.admin')

@section('page_eyebrow', 'GESTION DES COMPTES')
@section('page_title', 'Fiche utilisateur')
@section('page_description', 'Consultation des informations du compte, de son activité et des interventions administratives autorisées.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.users.index', $returnQuery) }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à la liste</a>
@endsection

@section('content')
    @if(session('success'))
        <div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-alert admin-alert--error" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <section class="admin-card admin-profile-hero" aria-labelledby="user-profile-title">
        <div class="admin-profile-hero__avatar">{{ mb_strtoupper(mb_substr($user->profile?->first_name ?: $user->name, 0, 1).mb_substr($user->profile?->last_name ?? '', 0, 1)) }}</div>
        <div class="admin-profile-hero__body">
            <span class="admin-card__eyebrow">Compte #{{ $user->id }}</span>
            <h3 id="user-profile-title">{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name }}</h3>
            <p>{{ $canViewSensitive ? $user->email : 'E-mail masqué · permission requise' }}</p>
            <div class="admin-chip-list">
                @forelse($user->roles as $role)<span class="admin-chip admin-chip--accent">{{ $role->display_name }}</span>@empty<span class="admin-muted">Aucun rôle attribué</span>@endforelse
            </div>
        </div>
        <div class="admin-users__hero-status">
            <span class="admin-status admin-status--{{ $user->account_status?->value }}">{{ $user->account_status?->value === 'active' ? 'Compte actif' : 'Compte suspendu' }}</span>
            <small>Inscrit le {{ $user->created_at?->format('d/m/Y à H:i') }}</small>
        </div>
    </section>

    @if($user->account_status?->value === 'suspended')
        <section class="admin-users__suspension-box" aria-labelledby="suspension-current-title">
            <h4 id="suspension-current-title"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Suspension en cours</h4>
            <div class="admin-users__suspension-meta">
                <p><strong>Motif :</strong> {{ match($user->suspension_reason) { 'suspected_fraud' => 'Fraude présumée', 'abusive_use' => 'Utilisation abusive', 'compromised_account' => 'Compte compromis ou suspect', 'identity_misrepresentation' => 'Usurpation ou fausses informations', 'policy_violation' => 'Non-respect des règles', 'administrative_decision' => 'Décision administrative motivée', 'other' => 'Autre', default => 'Non renseigné' } }}</p>
                <p><strong>Depuis :</strong> {{ $user->suspended_at?->format('d/m/Y H:i') ?? 'Date non renseignée' }}</p>
                <p><strong>Décision prise par :</strong> {{ $user->suspendedBy?->name ?? 'Administrateur non disponible' }}</p>
                <p><strong>Fin prévue :</strong> {{ $user->suspended_until?->format('d/m/Y H:i') ?? 'Jusqu’à nouvel examen' }}</p>
            </div>
            @if($user->suspension_note && $canViewSensitive)<p class="admin-users__internal-note"><strong>Note interne :</strong> {{ $user->suspension_note }}</p>@elseif($user->suspension_note)<p class="admin-users__internal-note">Note interne masquée · permission spécifique requise.</p>@endif
            @if($user->suspended_until && $user->suspended_until->isPast())
                <p class="admin-users__expiry-warning"><i class="fa-solid fa-clock" aria-hidden="true"></i> La date de fin est dépassée. La réactivation automatique sera exécutée par la tâche planifiée.</p>
            @endif
        </section>
    @endif

    <div class="admin-detail-grid admin-users__detail-grid">
        <section class="admin-card" aria-labelledby="user-general-title">
            <div class="admin-card__header">
                <div><span class="admin-card__eyebrow">Identité et accès</span><h3 id="user-general-title">Informations générales</h3></div>
            </div>
            <dl class="admin-definition-list">
                <div><dt>Identifiant interne</dt><dd>#{{ $user->id }}</dd></div>
                <div><dt>Nom du compte</dt><dd>{{ $user->name }}</dd></div>
                <div><dt>Prénom et nom</dt><dd>{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: 'Non renseignés' }}</dd></div>
                <div><dt>Adresse e-mail</dt><dd>{{ $canViewSensitive ? $user->email : 'Masquée · permission requise' }}</dd></div>
                <div><dt>Vérification e-mail</dt><dd>@if($user->email_verified_at)<span class="admin-verified">Vérifié le {{ $user->email_verified_at->format('d/m/Y H:i') }}</span>@else<span class="admin-muted">Non vérifié</span>@endif</dd></div>
                <div><dt>Téléphone</dt><dd>{{ $canViewSensitive ? ($user->profile?->phone ?: 'Non renseigné') : 'Masqué · permission requise' }}</dd></div>
                <div><dt>Localisation déclarée</dt><dd>{{ $canViewSensitive ? ($details['client']['location'] ?: 'Non renseignée') : 'Masquée · permission requise' }}</dd></div>
                <div><dt>Vérification téléphone</dt><dd><span class="admin-muted">Non prise en charge par le système actuel</span></dd></div>
                <div><dt>Type(s) de compte</dt><dd>{{ $user->roles->pluck('display_name')->join(', ') ?: 'Aucun rôle' }}</dd></div>
                <div><dt>État du compte</dt><dd>{{ $user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</dd></div>
                <div><dt>Inscription</dt><dd>{{ $user->created_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Dernière modification</dt><dd>{{ $user->updated_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Dernière activité web</dt><dd>@if($activityTrackingAvailable){{ $details['last_activity']?->format('d/m/Y H:i') ?? 'Aucune session web connue' }}@else<span class="admin-muted">Non disponible avec le pilote de session actuel</span>@endif</dd></div>
            </dl>
            <p class="admin-users__scope-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> L’e-mail, le téléphone, les rôles et permissions ne sont pas modifiables depuis cette fiche. Le prénom et le nom disposent d’une procédure de correction distincte. Les opérations sensibles restent dans leurs modules dédiés.</p>

            @can('update', $user)
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-role-form admin-users__action-panel" data-confirm="Confirmer la correction du prénom et du nom ? Cette modification sera enregistrée dans le journal d’audit et notifiée au titulaire.">
                    @csrf
                    @method('PATCH')
                    <h4><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Corriger le prénom et le nom</h4>
                    <div class="admin-form-grid">
                        <label class="admin-field">
                            <span>Prénom</span>
                            <input name="first_name" value="{{ old('first_name', $user->profile?->first_name ?? $user->name) }}" maxlength="100" required>
                        </label>
                        <label class="admin-field">
                            <span>Nom</span>
                            <input name="last_name" value="{{ old('last_name', $user->profile?->last_name ?? '') }}" maxlength="100" required>
                        </label>
                        <label class="admin-field admin-field--full">
                            <span>Motif de correction</span>
                            <textarea name="reason" rows="3" minlength="10" maxlength="500" required placeholder="Indiquez la demande ou la justification ayant motivé la correction.">{{ old('reason') }}</textarea>
                        </label>
                    </div>
                    <p class="admin-muted">Cette action ne modifie ni l’e-mail, ni le téléphone, ni le rôle, ni le statut du compte.</p>
                    <div class="admin-form-actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer la correction</button></div>
                </form>
            @endcan
        </section>

        @if($user->hasRole('client'))
            <section class="admin-card" aria-labelledby="user-client-title">
                <div class="admin-card__header">
                    <div><span class="admin-card__eyebrow">Profil client</span><h3 id="user-client-title">Activité du client</h3></div>
                </div>
                <dl class="admin-definition-list">
                    <div><dt>Demandes de services</dt><dd>{{ number_format($details['client']['requests']) }}</dd></div>
                    <div><dt>Devis reçus</dt><dd>{{ number_format($details['client']['quotations']) }}</dd></div>
                    <div><dt>Commandes</dt><dd>{{ number_format($details['client']['orders']) }}</dd></div>
                    <div><dt>Commandes terminées</dt><dd>{{ number_format($details['client']['completed_orders']) }}</dd></div>
                    <div><dt>Commandes annulées</dt><dd>{{ number_format($details['client']['cancelled_orders']) }}</dd></div>
                    <div><dt>Commandes en litige</dt><dd>{{ number_format($details['client']['disputes']) }}</dd></div>
                    <div><dt>Tickets de support</dt><dd>{{ number_format($details['client']['support_tickets']) }}</dd></div>
                    <div><dt>Signalements émis</dt><dd>{{ number_format($details['client']['reports']) }}</dd></div>
                </dl>
                <div class="admin-users__module-links">
                    @if(auth()->user()->hasPermissionTo('requests.view'))
                        <a href="{{ route('admin.service-requests.index', ['client_id' => $user->id]) }}"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Ses demandes</a>
                    @endif
                    @if(auth()->user()->hasPermissionTo('orders.view'))
                        <a href="{{ route('admin.orders.index', ['client_id' => $user->id]) }}"><i class="fa-solid fa-box" aria-hidden="true"></i> Ses commandes</a>
                    @endif
                    @if(auth()->user()->hasPermissionTo('payments.view'))
                        <a href="{{ route('admin.payments.index', ['client_id' => $user->id]) }}"><i class="fa-solid fa-credit-card" aria-hidden="true"></i> Ses paiements</a>
                    @endif
                    @if(auth()->user()->hasPermissionTo('support.manage'))
                        <a href="{{ route('admin.support.index', ['user_id' => $user->id]) }}"><i class="fa-solid fa-headset" aria-hidden="true"></i> Ses tickets support</a>
                    @endif
                    @if(auth()->user()->hasPermissionTo('reports.manage'))
                        <a href="{{ route('admin.reports.index', ['reporter_id' => $user->id]) }}"><i class="fa-solid fa-flag" aria-hidden="true"></i> Ses signalements</a>
                    @endif
                </div>
            </section>
        @endif

        @if($details['professional'])
            <section class="admin-card" aria-labelledby="user-professional-title">
                <div class="admin-card__header">
                    <div><span class="admin-card__eyebrow">Profil professionnel</span><h3 id="user-professional-title">Présentation et activité</h3></div>
                </div>
                <dl class="admin-definition-list">
                    <div><dt>Nom commercial</dt><dd>{{ $user->professionalProfile->business_name ?: 'Non renseigné' }}</dd></div>
                    <div><dt>Titre professionnel</dt><dd>{{ $user->professionalProfile->professional_title ?: 'Non renseigné' }}</dd></div>
                    <div><dt>Localisation</dt><dd>{{ collect([$user->professionalProfile->city, $user->professionalProfile->commune, $user->professionalProfile->province])->filter()->join(', ') ?: 'Non renseignée' }}</dd></div>
                    <div><dt>Vérification administrative</dt><dd>{{ match($details['professional']['verification']) { 'pending' => 'En attente', 'under_review' => 'En examen', 'verified' => 'Vérifié', 'rejected' => 'Refusé', default => 'Non définie' } }}</dd></div>
                    <div><dt>Complétude indicative</dt><dd>{{ $details['professional']['profile_complete'] ? 'Informations principales renseignées' : 'Informations à compléter' }}</dd></div>
                    <div><dt>Statut du profil professionnel</dt><dd>{{ match($details['professional']['status']) { 'draft' => 'Brouillon', 'active' => 'Actif', 'suspended' => 'Suspendu', 'closed' => 'Fermé', default => 'Non défini' } }}</dd></div>
                    <div><dt>Visibilité publique</dt><dd>{{ $details['professional']['visibility'] === 'public' ? 'Publique' : 'Privée' }}</dd></div>
                    <div><dt>Services publiés / total</dt><dd>{{ $details['professional']['published_services'] }} / {{ $details['professional']['services'] }}</dd></div>
                    <div><dt>Demandes reçues</dt><dd>{{ number_format($details['professional']['requests']) }}</dd></div>
                    <div><dt>Devis émis</dt><dd>{{ number_format($details['professional']['quotations']) }}</dd></div>
                    <div><dt>Commandes</dt><dd>{{ number_format($details['professional']['orders']) }}</dd></div>
                    <div><dt>Note moyenne / avis</dt><dd>{{ number_format((float) ($details['professional']['rating'] ?? 0), 2, ',', ' ') }} / {{ number_format($details['professional']['reviews']) }}</dd></div>
                    <div><dt>Disponibilité déclarée</dt><dd>{{ match($details['professional']['availability']) { 'available' => 'Disponible', 'unavailable' => 'Indisponible', 'unknown' => 'Non définie', default => 'Non renseignée' } }}</dd></div>
                    <div><dt>Compétences</dt><dd>{{ $user->professionalProfile->skills->pluck('name')->join(', ') ?: 'Aucune compétence associée' }}</dd></div>
                </dl>
                <div class="admin-users__module-links">
                    @if(auth()->user()->hasPermissionTo('admin.professionals.view'))
                        <a href="{{ route('admin.professionals.show', $user->professionalProfile) }}"><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Dossier professionnel</a>
                        <a href="{{ route('admin.verification.show', $user->professionalProfile) }}"><i class="fa-solid fa-shield-check" aria-hidden="true"></i> Vérification</a>
                    @endif
                    @if(auth()->user()->hasPermissionTo('requests.view'))
                        <a href="{{ route('admin.service-requests.index', ['professional_id' => $user->professionalProfile->id]) }}"><i class="fa-solid fa-inbox" aria-hidden="true"></i> Demandes reçues</a>
                    @endif
                    @if(auth()->user()->hasPermissionTo('orders.view'))
                        <a href="{{ route('admin.orders.index', ['professional_id' => $user->professionalProfile->id]) }}"><i class="fa-solid fa-box" aria-hidden="true"></i> Ses commandes</a>
                    @endif
                </div>
            </section>
        @endif

        <section class="admin-card" aria-labelledby="user-business-activity-title">
            <div class="admin-card__header">
                <div><span class="admin-card__eyebrow">Événements enregistrés</span><h3 id="user-business-activity-title">Activité métier</h3></div>
            </div>
            @if($details['business_activity']->isEmpty())
                <div class="admin-empty"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><strong>Aucune activité métier disponible</strong><span>Les événements affichés ici proviennent des demandes, devis, commandes et avis réellement enregistrés.</span></div>
            @else
                <ol class="admin-users__timeline">
                    @foreach($details['business_activity'] as $event)
                        <li><strong>{{ $event['type'] }}</strong><span>{{ $event['description'] }}</span><small>{{ $event['date']->format('d/m/Y H:i') }}</small></li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="admin-card" aria-labelledby="user-admin-activity-title">
            <div class="admin-card__header">
                <div><span class="admin-card__eyebrow">Traçabilité</span><h3 id="user-admin-activity-title">Historique administratif</h3></div>
                @if(auth()->user()->hasPermissionTo('audit.view'))<a class="admin-link" href="{{ route('admin.audit.index', ['subject_type' => $user->getMorphClass(), 'subject_id' => $user->id]) }}">Journal d’audit complet</a>@endif
            </div>
            <p class="admin-users__scope-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Les connexions détaillées, échecs d’authentification et changements de mot de passe ne sont pas historisés comme événements dans le système actuel. La dernière session web connue est affichée séparément lorsqu’elle est disponible.</p>
            @if($details['administrative_activity']->isEmpty())
                <p class="admin-muted">Aucune intervention administrative enregistrée pour ce compte.</p>
            @else
                <ol class="admin-users__timeline">
                    @foreach($details['administrative_activity'] as $event)
                        <li>
                            <strong>{{ str($event->action)->afterLast('.')->replace('_', ' ')->ucfirst() }}</strong>
                            <small>{{ $event->created_at?->format('d/m/Y H:i') }} · {{ $event->user?->name ?? 'Système / administrateur supprimé' }}</small>
                            @php($safeMetadata = collect($event->metadata ?? [])->only(['previous_status', 'new_status', 'reason_code', 'duration', 'suspended_until', 'previous_reason_code', 'previous_suspended_until']))
                            @if($safeMetadata->isNotEmpty())<p>{{ $safeMetadata->map(fn ($value, $key) => str($key)->replace('_', ' ')->ucfirst().': '.(is_scalar($value) ? $value : json_encode($value)))->join(' · ') }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="admin-card" aria-labelledby="user-actions-title">
            <div class="admin-card__header">
                <div><span class="admin-card__eyebrow">Interventions autorisées</span><h3 id="user-actions-title">Gestion du compte</h3></div>
            </div>
            <p class="admin-muted">Chaque intervention est contrôlée côté serveur, enregistrée dans le journal d’audit et ne supprime aucune commande ni donnée financière. Pour un professionnel, la suspension retire l’éligibilité à la visibilité publique et aux nouvelles demandes ; les commandes déjà engagées suivent leur propre cycle.</p>
            <p class="admin-users__scope-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Le modèle actuel distingue uniquement les comptes actifs et suspendus. La désactivation, la fermeture définitive et la suppression/anonymisation nécessitent un cycle métier dédié ; aucun bouton de suppression n’est donc exposé ici.</p>

            @if($user->isActive())
                @can('suspend', $user)
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="admin-role-form admin-users__action-panel" data-confirm="Confirmer la suspension de ce compte ? Les sessions web et les jetons API seront révoqués. Les opérations commerciales en cours ne seront pas supprimées.">
                        @csrf
                        <h4><i class="fa-solid fa-user-slash" aria-hidden="true"></i> Suspendre ce compte</h4>
                        <div class="admin-form-grid">
                            <label class="admin-field">
                                <span>Motif de suspension</span>
                                <select name="reason_code" required>
                                    <option value="">Sélectionner un motif</option>
                                    <option value="suspected_fraud" @selected(old('reason_code') === 'suspected_fraud')>Fraude présumée</option>
                                    <option value="abusive_use" @selected(old('reason_code') === 'abusive_use')>Utilisation abusive</option>
                                    <option value="compromised_account" @selected(old('reason_code') === 'compromised_account')>Compte compromis ou suspect</option>
                                    <option value="identity_misrepresentation" @selected(old('reason_code') === 'identity_misrepresentation')>Usurpation ou fausses informations</option>
                                    <option value="policy_violation" @selected(old('reason_code') === 'policy_violation')>Non-respect des règles</option>
                                    <option value="administrative_decision" @selected(old('reason_code') === 'administrative_decision')>Décision administrative motivée</option>
                                    <option value="other" @selected(old('reason_code') === 'other')>Autre</option>
                                </select>
                            </label>
                            <label class="admin-field">
                                <span>Durée</span>
                                <select name="duration" required>
                                    <option value="until_review" @selected(old('duration', 'until_review') === 'until_review')>Jusqu’à nouvel examen</option>
                                    <option value="7d" @selected(old('duration') === '7d')>7 jours</option>
                                    <option value="30d" @selected(old('duration') === '30d')>30 jours</option>
                                    <option value="custom" @selected(old('duration') === 'custom')>Jusqu’à une date précise</option>
                                </select>
                            </label>
                            <label class="admin-field">
                                <span>Date et heure de fin (si date précise)</span>
                                <input type="datetime-local" name="suspended_until" value="{{ old('suspended_until') }}" min="{{ now()->format('Y-m-d\\TH:i') }}">
                                <small>Utilisée uniquement si la durée « date précise » est choisie.</small>
                            </label>
                            <label class="admin-field admin-field--full">
                                <span>Note interne (obligatoire si motif « Autre »)</span>
                                <textarea name="note" rows="3" maxlength="1000" placeholder="Contexte utile à l’équipe d’administration, non visible publiquement.">{{ old('note') }}</textarea>
                            </label>
                        </div>
                        <div class="admin-form-actions"><button class="button button--danger" type="submit"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Confirmer la suspension</button></div>
                    </form>
                @else
                    <p class="admin-muted">Tu ne disposes pas de l’autorisation nécessaire pour suspendre ce compte, ou ce compte bénéficie d’une protection administrative.</p>
                @endcan
            @else
                @can('activate', $user)
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="admin-role-form admin-users__action-panel" data-confirm="Confirmer la réactivation de ce compte ? Cette opération sera journalisée.">
                        @csrf
                        <h4><i class="fa-solid fa-user-check" aria-hidden="true"></i> Réactiver ce compte</h4>
                        <label class="admin-field admin-field--full">
                            <span>Motif de réactivation</span>
                            <textarea name="reason" rows="3" minlength="10" maxlength="500" required placeholder="Expliquez pourquoi le compte peut de nouveau être actif.">{{ old('reason') }}</textarea>
                        </label>
                        <div class="admin-form-actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Confirmer la réactivation</button></div>
                    </form>
                @else
                    <p class="admin-muted">Tu ne disposes pas de l’autorisation nécessaire pour réactiver ce compte, ou ce compte bénéficie d’une protection administrative.</p>
                @endcan
            @endif
        </section>
    </div>
@endsection
