@extends('layouts.professional')

@section('title', 'Mes devis — PROXIWORK')
@section('page_heading', 'Mes devis')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Espace professionnel</p>
            <h1>Mes devis</h1>
            <p class="page-lead">Suivez les propositions envoyées aux clients et leur état.</p>
        </div>
    </div>

    @if ($quotations->isEmpty())
        <x-empty-state
            title="Aucun devis envoyé"
            description="Lorsqu'une demande reçoit votre proposition, le devis apparaîtra ici."
            icon="fa-solid fa-file-invoice-dollar"
        />
    @else
        <div class="table-responsive surface-card">
            <table class="data-table">
                <thead><tr><th>Demande</th><th>Client</th><th>Montant proposé</th><th>Statut</th><th>Validité</th><th></th></tr></thead>
                <tbody>
                    @foreach ($quotations as $quotation)
                        <tr>
                            <td><strong>{{ $quotation->serviceRequest?->title ?? 'Demande' }}</strong><p class="table-secondary">Devis #{{ $quotation->getKey() }}</p></td>
                            <td>{{ $quotation->serviceRequest?->client?->name ?? 'Client' }}</td>
                            <td>
                                @if ($quotation->currentOffer)
                                    {{ number_format((float) $quotation->currentOffer->amount, 2) }} {{ $quotation->currentOffer->currency }}
                                @else
                                    En attente d'offre
                                @endif
                            </td>
                            <td><span class="badge badge--neutral">{{ ucfirst($quotation->status->value) }}</span></td>
                            <td>{{ $quotation->currentOffer?->valid_until?->format('d/m/Y') ?? '—' }}</td>
                            <td><a class="button button--ghost button--sm" href="{{ route('professional.quotes.show', $quotation) }}">Consulter</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="professional-pagination">{{ $quotations->links() }}</div>
    @endif
@endsection
