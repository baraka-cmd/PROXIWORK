@extends('layouts.professional')

@section('title', 'Mes devis — PROXIWORK')
@section('page_heading', 'Mes devis')

@push('head')
    @vite(['resources/css/pages/professional/quotes.css'])
@endpush

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Espace professionnel</p>
            <h1>Mes devis</h1>
            <p class="page-lead">Suivez les propositions envoyées aux clients et leur état.</p>
        </div>
    </div>

    <form class="professional-quote-search surface-card" method="GET" action="{{ route('professional.quotes.index') }}" aria-label="Rechercher et filtrer les devis">
        @if ($actionRequired)
            <input type="hidden" name="action_required" value="1">
        @endif
        <div class="professional-quote-search__grid">
            <div>
                <label for="professional-quote-q">Recherche</label>
                <input id="professional-quote-q" name="q" type="search" maxlength="120" value="{{ $filters['q'] ?? '' }}" placeholder="Demande ou nom du client">
            </div>
            <div>
                <label for="professional-quote-status">Statut</label>
                <select id="professional-quote-status" name="status">
                    <option value="">Tous les statuts</option>
                    @foreach (\App\Enums\QuotationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ match($status->value) { 'draft' => 'Brouillon', 'sent' => 'Envoyé au client', 'negotiating' => 'En négociation', 'accepted' => 'Accepté', 'rejected' => 'Refusé', 'expired' => 'Expiré', 'withdrawn' => 'Retiré', default => ucfirst(str_replace('_', ' ', $status->value)) } }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="professional-quote-from">Créé du</label>
                <input id="professional-quote-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div>
                <label for="professional-quote-to">Au</label>
                <input id="professional-quote-to" type="date" name="to" min="{{ $filters['from'] ?? '' }}" value="{{ $filters['to'] ?? '' }}">
            </div>
        </div>
        <div class="professional-quote-search__actions">
            <button class="button button--primary" type="submit">Appliquer</button>
            <a class="button button--ghost" href="{{ route('professional.quotes.index', $actionRequired ? ['action_required' => 1] : []) }}">Réinitialiser</a>
        </div>
    </form>

    <nav class="professional-quote-filters" aria-label="Filtrer les devis">
        <a href="{{ route('professional.quotes.index', collect($filters)->except('action_required')->all()) }}" class="professional-quote-filter-chip {{ ! $actionRequired ? 'is-active' : '' }}">Tous les devis</a>
        <a href="{{ route('professional.quotes.index', array_merge(collect($filters)->except('action_required')->all(), ['action_required' => 1])) }}" class="professional-quote-filter-chip {{ $actionRequired ? 'is-active' : '' }}">Action requise</a>
    </nav>

    @if ($quotations->isEmpty())
        <x-empty-state
            :title="$actionRequired ? 'Aucun devis à traiter' : 'Aucun devis envoyé'"
            :description="$actionRequired ? 'Aucune contre-proposition client ne nécessite actuellement votre réponse.' : 'Lorsqu’une demande reçoit votre proposition, le devis apparaîtra ici.'"
            icon="fa-solid fa-file-invoice-dollar"
        />
    @else
        @if ($actionRequired)
            <div class="professional-quote-alert professional-quote-alert--action" role="status">
                <strong>Votre réponse est attendue</strong>
                <p>Ces devis ont une contre-proposition du client en cours. Vérifiez les conditions et répondez depuis le détail du devis.</p>
            </div>
        @endif
        <div class="table-responsive surface-card professional-quote-table-wrap">
            <table class="data-table professional-quote-table">
                <thead><tr><th>Demande</th><th>Client</th><th>Montant proposé</th><th>Statut</th><th>Validité</th><th></th></tr></thead>
                <tbody>
                    @foreach ($quotations as $quotation)
                        <tr @class(['professional-quote-row--priority' => $quotation->status === \App\Enums\QuotationStatus::NEGOTIATING && $quotation->currentOffer?->actor_type === \App\Enums\QuotationOfferActor::CLIENT && ($quotation->currentOffer?->valid_until?->isFuture() ?? false)])>
                            <td data-label="Demande"><strong>{{ $quotation->serviceRequest?->title ?? 'Demande' }}</strong><p class="table-secondary">Devis #{{ $quotation->getKey() }}</p></td>
                            <td data-label="Client">{{ $quotation->serviceRequest?->client?->name ?? 'Client' }}</td>
                            <td data-label="Montant proposé">
                                @if ($quotation->currentOffer)
                                    <strong>{{ number_format((float) $quotation->currentOffer->amount, 2, ',', ' ') }} {{ $quotation->currentOffer->currency }}</strong>
                                    @if ($quotation->currentOffer->actor_type === \App\Enums\QuotationOfferActor::CLIENT)
                                        <small class="professional-quote-current-actor">Contre-proposition du client</small>
                                    @endif
                                @else
                                    En attente d'offre
                                @endif
                            </td>
                            <td data-label="Statut"><span class="badge badge--neutral">{{ $quotation->status->label() }}</span></td>
                            <td data-label="Validité">{{ $quotation->currentOffer?->valid_until?->format('d/m/Y') ?? '—' }}</td>
                            <td data-label="Action"><a class="button button--ghost button--sm" href="{{ route('professional.quotes.show', $quotation) }}">Consulter</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="professional-pagination">{{ $quotations->links() }}</div>
    @endif
@endsection
