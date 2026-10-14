@extends('layouts.professional')

@section('title', 'Détail du revenu')

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/revenues.css'])
    @endpush

    <div class="revenue-page">
        <header class="revenue-page__header">
            <div>
                <p class="revenue-kicker">FINANCES · JUSTIFICATIF</p>
                <h1>Détail de l’opération COM-{{ $commission->id }}</h1>
                <p class="revenue-muted">Montants issus de la commission enregistrée côté serveur. Aucune modification financière n’est possible ici.</p>
            </div>
            <a class="revenue-button revenue-button--secondary" href="{{ route('professional.revenues.index', request()->query()) }}">Retour aux revenus</a>
        </header>

        <section class="revenue-card">
            <div class="revenue-detail__headline">
                <div>
                    <span class="revenue-muted">Montant net enregistré</span>
                    <strong>{{ number_format((float) $commission->net_amount, 2, ',', ' ') }} {{ $commission->currency }}</strong>
                </div>
                <span class="revenue-status">{{ $commission->status->value === 'posted' ? 'Comptabilisée' : 'Annulée / corrigée' }}</span>
            </div>
            <dl class="revenue-detail-grid">
                <div><dt>Référence</dt><dd>COM-{{ $commission->id }}</dd></div>
                <div><dt>Commande</dt><dd>{{ $commission->order?->order_number ?? '#'.$commission->order_id }}</dd></div>
                <div><dt>Service</dt><dd>{{ $serviceTitle }}</dd></div>
                <div><dt>Date de comptabilisation</dt><dd>{{ $commission->posted_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt>Montant brut</dt><dd>{{ number_format((float) $commission->gross_amount, 2, ',', ' ') }} {{ $commission->currency }}</dd></div>
                <div><dt>Taux enregistré</dt><dd>{{ number_format((float) $commission->commission_rate, 2, ',', ' ') }} %</dd></div>
                <div><dt>Frais de plateforme</dt><dd>{{ number_format((float) $commission->commission_amount, 2, ',', ' ') }} {{ $commission->currency }}</dd></div>
                <div><dt>Calcul</dt><dd>{{ $commission->calculation_type }}</dd></div>
                <div><dt>État</dt><dd>{{ $commission->status->value }}</dd></div>
                <div><dt>Date de correction</dt><dd>{{ $commission->reversed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt>Paiement associé</dt><dd>{{ $commission->payment?->id ? 'PAY-'.$commission->payment->id : '—' }}</dd></div>
                <div><dt>Paiement confirmé le</dt><dd>{{ $commission->payment?->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>
            <p class="revenue-notice">Vérification arithmétique : montant brut = frais de plateforme + montant net. La date du paiement peut différer de la date de comptabilisation.</p>
        </section>

        <section class="revenue-card">
            <h2>Historique disponible</h2>
            <p class="revenue-muted">Les événements proviennent des enregistrements financiers existants. Ils ne remplacent pas un registre comptable complet des remboursements et corrections.</p>
            <div class="revenue-table-wrap">
                <table class="revenue-table">
                    <thead><tr><th>Événement</th><th>Date</th><th>Référence</th><th>État / détail</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Commission enregistrée</td>
                            <td>{{ $commission->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>COM-{{ $commission->id }}</td>
                            <td>{{ $commission->status->value }}</td>
                        </tr>
                        @if ($commission->reversed_at)
                            <tr>
                                <td>Correction de commission</td>
                                <td>{{ $commission->reversed_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $commission->reversal_of_id ? 'COM-'.$commission->reversal_of_id : '—' }}</td>
                                <td>La commission est marquée comme annulée.</td>
                            </tr>
                        @endif
                        @foreach ($walletTransactions as $transaction)
                            <tr>
                                <td>Mouvement du portefeuille</td>
                                <td>{{ $transaction->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>WAL-{{ $transaction->id }}</td>
                                <td>{{ $transaction->description ?: $transaction->type->value }} · {{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</td>
                            </tr>
                        @endforeach
                        @if ($commission->payment)
                            @foreach ($commission->payment->events as $event)
                                <tr>
                                    <td>Événement de paiement</td>
                                    <td>{{ $event->occurred_at?->format('d/m/Y H:i') ?? $event->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>PAY-{{ $commission->payment->id }}</td>
                                    <td>{{ $event->event_type }} · {{ $event->to_status ?? '—' }}</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section class="revenue-card">
            <h2>Remboursements associés</h2>
            <p class="revenue-muted">Les demandes et remboursements sont affichés à partir du dossier de paiement lié à cette commission. Une demande n’est pas une sortie de fonds confirmée; un remboursement confirmé ne constitue pas, à lui seul, une écriture de correction de commission ou de portefeuille.</p>
            @if ($commission->payment?->refunds?->isNotEmpty())
                <div class="revenue-table-wrap">
                    <table class="revenue-table">
                        <thead><tr><th>Référence</th><th>Demandé le</th><th>Traité le</th><th>Montant</th><th>État</th></tr></thead>
                        <tbody>
                            @foreach ($commission->payment->refunds as $refund)
                                <tr>
                                    <td>{{ $refund->reference }}</td>
                                    <td>{{ $refund->requested_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $refund->processed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ number_format((float) $refund->amount, 2, ',', ' ') }} {{ $refund->currency }}</td>
                                    <td><span class="revenue-status {{ in_array($refund->status->value, ['requested', 'processing'], true) ? 'revenue-status--pending' : (in_array($refund->status->value, ['failed', 'rejected', 'cancelled'], true) ? 'revenue-status--reversed' : '') }}">{{ match ($refund->status->value) { 'requested' => 'Demandé', 'processing' => 'En traitement', 'succeeded' => 'Confirmé', 'failed' => 'Échoué', 'rejected' => 'Refusé', 'cancelled' => 'Annulé', default => $refund->status->value } }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="revenue-empty">Aucun dossier de remboursement associé à ce paiement.</p>
            @endif
        </section>

        <nav class="revenue-related-links" aria-label="Autres pages financières">
            <a href="{{ route('professional.wallet') }}">Consulter le portefeuille</a>
            <a href="{{ route('professional.withdrawals') }}">Consulter les retraits</a>
        </nav>
    </div>
@endsection
