@extends('layouts.client')

@section('title', 'Demande PW-REQ-' . str_pad((string) $serviceRequest->id, 6, '0', STR_PAD_LEFT))

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
    @vite('resources/css/pages/client/service-requests.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
<div class="client-module client-requests-page">
    <div class="client-module__header client-requests-header">
        <div>
            <p class="client-module__eyebrow">SUIVI DE LA DEMANDE · PW-REQ-{{ str_pad((string) $serviceRequest->id, 6, '0', STR_PAD_LEFT) }}</p>
            <h1>{{ $serviceRequest->title }}</h1>
            <p>{{ $serviceRequest->service?->title ?? 'Service indisponible' }} · {{ $serviceRequest->professional?->user?->name ?? 'Professionnel indisponible' }}</p>
        </div>
        <span class="request-status-badge request-status-badge--{{ $serviceRequest->status->value }}">
            {{ $serviceRequest->status->label() }}
        </span>
    </div>

    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if($errors->any())
        <div class="client-form-alert" role="alert">
            <strong>La demande n’a pas pu être traitée.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="client-detail-grid">
        <section class="client-panel">
            <div class="client-panel__header">
                <div><p class="client-module__eyebrow">VOTRE BESOIN</p><h2>Détails de la demande</h2></div>
                <span class="request-card__reference">#{{ $serviceRequest->id }}</span>
            </div>
            <p class="client-detail-text">{{ $serviceRequest->description }}</p>
            <dl class="client-definition-list">
                <div><dt>Créée le</dt><dd>{{ $serviceRequest->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                <div><dt>Envoyée le</dt><dd>{{ $serviceRequest->requested_at?->format('d/m/Y à H:i') ?? 'Pas encore envoyée' }}</dd></div>
                <div><dt>Budget minimum</dt><dd>{{ $serviceRequest->budget_min !== null ? number_format((float) $serviceRequest->budget_min, 2, ',', ' ') . ' ' . ($serviceRequest->currency ?? '') : 'Non précisé' }}</dd></div>
                <div><dt>Budget maximum</dt><dd>{{ $serviceRequest->budget_max !== null ? number_format((float) $serviceRequest->budget_max, 2, ',', ' ') . ' ' . ($serviceRequest->currency ?? '') : 'Non précisé' }}</dd></div>
                <div><dt>Date souhaitée</dt><dd>{{ $serviceRequest->desired_at?->format('d/m/Y à H:i') ?? 'Flexible / à convenir' }}</dd></div>
                <div><dt>Lieu de prestation</dt><dd>{{ $serviceRequest->execution_mode->requiresClientAddress() ? ($serviceRequest->addressSnapshot?->city ?: ($serviceRequest->address?->city ?: 'À préciser')) : 'À distance' }}{{ $serviceRequest->execution_mode->requiresClientAddress() && ($serviceRequest->addressSnapshot?->province ?: $serviceRequest->address?->province) ? ', '.($serviceRequest->addressSnapshot?->province ?: $serviceRequest->address?->province) : '' }}</dd></div>
            </dl>
        </section>

        <aside class="client-panel">
            <p class="client-module__eyebrow">VOTRE INTERLOCUTEUR</p>
            <h2>Professionnel</h2>
            <div class="client-request-professional">
                <div class="client-avatar">{{ strtoupper(substr($serviceRequest->professional?->user?->name ?? 'P', 0, 1)) }}</div>
                <div>
                    <strong>{{ $serviceRequest->professional?->user?->name ?? 'Professionnel indisponible' }}</strong>
                    <span>{{ $serviceRequest->professional?->professional_title ?? 'Profil professionnel' }}</span>
                </div>
            </div>
            <p class="request-card__description">Votre demande reste distincte du devis, de la commande et du paiement. Chaque élément possède son propre état.</p>
            @if($conversation)
                <a class="btn btn-secondary" href="{{ route('client.messages.show', $conversation) }}">
                    <i class="fa-regular fa-message" aria-hidden="true"></i> Ouvrir la conversation
                </a>
            @else
                @if ($serviceRequest->professional)
                    <form method="POST" action="{{ route('client.messages.start', $serviceRequest->professional) }}">
                        @csrf
                        <input type="hidden" name="service_request_id" value="{{ $serviceRequest->id }}">
                        <button class="btn btn-secondary" type="submit"><i class="fa-regular fa-message" aria-hidden="true"></i> Contacter au sujet de cette demande</button>
                    </form>
                @else
                    <a class="btn btn-secondary" href="{{ route('client.messages.index') }}"><i class="fa-regular fa-message" aria-hidden="true"></i> Accéder à la messagerie</a>
                @endif
            @endif
        </aside>
    </div>


    @if(in_array($serviceRequest->status->value, ['requested', 'quoted'], true))
        <section class="client-panel">
            <p class="client-module__eyebrow">LIEU D’EXÉCUTION</p>
            <h2>Adresse de la prestation</h2>
            @if($serviceRequest->execution_mode->requiresClientAddress())
            <p>Le lieu affiché ci-dessous est un instantané de cette demande : modifier votre carnet ne le changera pas automatiquement. Avant l’acceptation du devis, vous pouvez choisir une adresse et l’enregistrer à nouveau pour mettre à jour le lieu de cette demande. Le processus actuel exige une adresse valide pour créer la commande.</p>
            @if($serviceRequest->addressSnapshot)
                <div class="client-request-location-snapshot">
                    <strong>{{ $serviceRequest->addressSnapshot->label }}</strong>
                    <p>{{ $serviceRequest->addressSnapshot->address_line_1 }}{{ $serviceRequest->addressSnapshot->address_line_2 ? ', '.$serviceRequest->addressSnapshot->address_line_2 : '' }}</p>
                    <p>{{ collect([$serviceRequest->addressSnapshot->neighborhood, $serviceRequest->addressSnapshot->commune, $serviceRequest->addressSnapshot->city, $serviceRequest->addressSnapshot->province, $serviceRequest->addressSnapshot->country_code])->filter()->join(', ') }}</p>
                    @if($serviceRequest->addressSnapshot->landmark)<p><strong>Repère :</strong> {{ $serviceRequest->addressSnapshot->landmark }}</p>@endif
                    @if($serviceRequest->addressSnapshot->access_instructions)<p><strong>Instructions d’accès :</strong> {{ $serviceRequest->addressSnapshot->access_instructions }}</p>@endif
                </div>
            @endif
            @if($addresses->isEmpty())
                <div class="client-form-note">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <span>Vous n’avez pas encore d’adresse enregistrée. Ajoutez-en une dans votre carnet d’adresses, puis revenez ici.</span>
                </div>
                <a class="btn btn-secondary" href="{{ route('client.addresses.create') }}">Ajouter une adresse</a>
            @else
                <form method="POST" action="{{ route('client.requests.address', $serviceRequest) }}" class="request-address-form">
                    @csrf
                    <div class="client-field">
                        <label for="request-address-id">Adresse d’exécution</label>
                        <select id="request-address-id" name="address_id" required>
                            <option value="">Choisir une adresse</option>
                            @foreach($addresses as $address)
                                <option value="{{ $address->id }}" @selected((string) old('address_id', $serviceRequest->address_id) === (string) $address->id)>
                                    {{ $address->label }} — {{ $address->city }}{{ $address->province ? ', '.$address->province : '' }} · {{ $address->address_line_1 }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('address_id')<small class="client-field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-secondary" type="submit">Enregistrer l’adresse</button>
                </form>
            @endif
            @else
                <div class="client-form-note"><i class="fa-solid fa-laptop" aria-hidden="true"></i><span>Cette demande concerne une prestation à distance. Aucune adresse physique n’est requise ; aucune adresse du carnet n’est partagée avec le professionnel pour cette prestation.</span></div>
            @endif
        </section>
    @endif

    <section class="client-panel">
        <div class="client-panel__header">
            <div><p class="client-module__eyebrow">TRAÇABILITÉ</p><h2>Historique de la demande</h2></div>
            <span class="request-card__reference">{{ $serviceRequest->statusHistories->count() }} événement(s)</span>
        </div>
        <ol class="client-timeline">
            @forelse($serviceRequest->statusHistories as $history)
                @php($historyStatus = \App\Enums\ServiceRequestStatus::tryFrom($history->to_status))
                <li>
                    <strong>{{ $historyStatus?->label() ?? ucfirst(str_replace('_', ' ', $history->to_status)) }}</strong>
                    <span>{{ $history->created_at?->format('d/m/Y à H:i') ?? 'Date indisponible' }}</span>
                    <p class="request-history-actor">{{ $history->actor?->name ?? 'Système PROXIWORK' }}</p>
                    @if($history->reason && ! str_starts_with($history->reason, 'Quotation rejected by client'))
                        <p>{{ $history->reason }}</p>
                    @endif
                </li>
            @empty
                <li>
                    <strong>{{ $serviceRequest->status->label() }}</strong>
                    <span>{{ $serviceRequest->created_at?->format('d/m/Y à H:i') ?? 'Date indisponible' }}</span>
                    <p>La demande a été enregistrée. Les événements détaillés seront affichés ici.</p>
                </li>
            @endforelse
        </ol>
    </section>

    @if($serviceRequest->quotation)
        <section class="client-panel client-panel--accent">
            <div class="client-panel__header">
                <div><p class="client-module__eyebrow">ÉTAPE COMMERCIALE</p><h2>Devis associé</h2></div>
                <span class="request-card__reference">{{ $serviceRequest->quotation->status->label() }}</span>
            </div>
            @if($serviceRequest->quotation->currentOffer)
                <p class="client-detail-highlight">{{ number_format((float) $serviceRequest->quotation->currentOffer->amount, 2, ',', ' ') }} {{ $serviceRequest->quotation->currentOffer->currency }}</p>
                <p>Montant de l’offre courante. Le montant contractuel sera celui de l’offre acceptée et enregistrée dans la commande.</p>
            @endif
            <a class="btn btn-primary" href="{{ route('client.quotes.show', $serviceRequest->quotation) }}">
                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Consulter le devis
            </a>
        </section>
    @endif

    @if($serviceRequest->order)
        <section class="client-panel">
            <div class="client-panel__header">
                <div><p class="client-module__eyebrow">SUITE DU PROCESSUS</p><h2>Commande associée</h2></div>
                <span class="request-card__reference">{{ $serviceRequest->order->order_number }}</span>
            </div>
            <p>Le devis accepté a conduit à une commande distincte. Consultez son état et les prochaines étapes dans l’espace des commandes.</p>
            <a class="btn btn-primary" href="{{ route('client.orders.show', $serviceRequest->order) }}">Voir la commande</a>
        </section>
    @endif

    <div class="client-action-bar">
        @if($serviceRequest->status === \App\Enums\ServiceRequestStatus::DRAFT)
            <a class="btn btn-secondary" href="{{ route('client.requests.edit', $serviceRequest) }}">
                <i class="fa-solid fa-pen" aria-hidden="true"></i> Modifier le brouillon
            </a>
            <form method="POST" action="{{ route('client.requests.submit', $serviceRequest) }}">
                @csrf
                <button class="btn btn-primary" type="submit" data-confirm-submit="Envoyer cette demande au professionnel ?" data-loading-label="Envoi…">
                    <i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Envoyer la demande
                </button>
            </form>
        @endif
        @if(in_array($serviceRequest->status, [\App\Enums\ServiceRequestStatus::DRAFT, \App\Enums\ServiceRequestStatus::REQUESTED], true))
            <form method="POST" action="{{ route('client.requests.cancel', $serviceRequest) }}">
                @csrf
                <button class="btn btn-danger" type="submit" data-confirm-submit="Voulez-vous vraiment annuler cette demande ?" data-loading-label="Annulation…">Annuler la demande</button>
            </form>
        @endif
        <a class="btn btn-secondary" href="{{ route('client.requests.index') }}">Retour à mes demandes</a>
    </div>
</div>
@endsection
