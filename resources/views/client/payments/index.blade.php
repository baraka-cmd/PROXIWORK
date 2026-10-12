@extends('layouts.client')

@section('title', 'Mes paiements')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush

<div class="client-module client-payments">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">ESPACE FINANCIER PERSONNEL</p>
            <h1>Mes paiements</h1>
            <p>Suivez vos tentatives, confirmations, reçus de simulation et demandes de remboursement.</p>
        </div>
        <span class="payment-simulation-badge"><i class="fa-solid fa-flask" aria-hidden="true"></i> Mode simulation</span>
    </header>

    <aside class="payment-simulation-notice" role="note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <p><strong>Simulation uniquement.</strong> Aucun argent réel n’est transféré. Une réussite affichée ici confirme uniquement le scénario enregistré par PROXIWORK.</p>
    </aside>

    <section class="payment-metrics" aria-label="Résumé financier">
        <article class="payment-metric">
            <span class="payment-metric__icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
            <div><h2>À régler</h2><p>Commandes en attente de paiement</p></div>
            <div class="payment-metric__totals">
                @forelse($dueByCurrency as $total)
                    <strong>{{ number_format((float) $total->total, 2, ',', ' ') }} <small>{{ $total->currency }}</small></strong>
                @empty
                    <strong>—</strong>
                @endforelse
            </div>
        </article>
        <article class="payment-metric">
            <span class="payment-metric__icon payment-metric__icon--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <div><h2>Paiements confirmés</h2><p>Total brut des réussites simulées, avant remboursements</p></div>
            <div class="payment-metric__totals">
                @forelse($confirmedByCurrency as $total)
                    <strong>{{ number_format((float) $total->total, 2, ',', ' ') }} <small>{{ $total->currency }}</small></strong>
                @empty
                    <strong>—</strong>
                @endforelse
            </div>
        </article>
        <article class="payment-metric">
            <span class="payment-metric__icon payment-metric__icon--pending"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
            <div><h2>En traitement</h2><p>Opérations qui nécessitent encore une vérification</p></div>
            <div class="payment-metric__totals">
                @forelse($processingByCurrency as $total)
                    <strong>{{ number_format((float) $total->total, 2, ',', ' ') }} <small>{{ $total->currency }}</small></strong>
                @empty
                    <strong>—</strong>
                @endforelse
            </div>
        </article>
        <article class="payment-metric">
            <span class="payment-metric__icon payment-metric__icon--refund"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
            <div><h2>Remboursements demandés</h2><p>Montants soumis à examen, non encore remboursés</p></div>
            <div class="payment-metric__totals">
                @forelse($refundRequestsByCurrency as $total)
                    <strong>{{ number_format((float) $total->total, 2, ',', ' ') }} <small>{{ $total->currency }}</small></strong>
                @empty
                    <strong>—</strong>
                @endforelse
            </div>
        </article>
    </section>

    <section class="client-panel payment-filter-panel">
        <div class="payment-section-heading">
            <div><h2>Rechercher un paiement</h2><p>Les dates de création et de confirmation sont filtrées séparément.</p></div>
        </div>
        <form method="GET" action="{{ route('client.payments.index') }}" class="payment-filter-form">
            <label class="payment-filter-form__search">
                <span>Référence paiement, commande ou service</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Ex. PAY-00000012, CMD-…">
            </label>
            <label><span>Statut du paiement</span>
                <select name="status">
                    <option value="">Tous les statuts</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Moyen simulé</span>
                <select name="method">
                    <option value="">Tous les moyens</option>
                    @foreach($methodLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['method'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Fournisseur</span>
                <select name="provider">
                    <option value="">Tous les fournisseurs</option>
                    <option value="fake" @selected(($filters['provider'] ?? '') === 'fake')>Simulateur PROXIWORK</option>
                </select>
            </label>
            <label><span>Devise</span>
                <select name="currency">
                    <option value="">Toutes les devises</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Tentative</span>
                <select name="transaction_status">
                    <option value="">Tous les résultats</option>
                    @foreach($transactionStatusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['transaction_status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <fieldset class="payment-date-filter">
                <legend>Date de création</legend>
                <label><span>Du</span><input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></label>
                <label><span>Au</span><input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></label>
            </fieldset>
            <fieldset class="payment-date-filter">
                <legend>Date de confirmation</legend>
                <label><span>Du</span><input type="date" name="confirmed_from" value="{{ $filters['confirmed_from'] ?? '' }}"></label>
                <label><span>Au</span><input type="date" name="confirmed_to" value="{{ $filters['confirmed_to'] ?? '' }}"></label>
            </fieldset>
            <div class="payment-filter-actions">
                <p><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Les résultats et les indicateurs sont limités à votre compte. Les totaux sont séparés par devise.</p>
                <div><a class="btn btn-secondary" href="{{ route('client.payments.index') }}">Réinitialiser</a><button class="btn btn-primary" type="submit">Appliquer les filtres</button></div>
            </div>
        </form>
    </section>

    <section class="client-panel payment-results">
        <div class="payment-section-heading">
            <div><h2>Historique</h2><p>{{ $payments->total() }} paiement(s) trouvé(s)</p></div>
        </div>
        @if($payments->isEmpty())
            <div class="payment-empty-state">
                <span><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                <h3>Aucun paiement correspondant</h3>
                <p>Essayez d’ajuster vos filtres. Les paiements apparaîtront ici après leur initialisation.</p>
            </div>
        @else
            <div class="payment-table-wrap">
                <table class="payment-table">
                    <thead><tr><th>Références</th><th>Montant</th><th>Moyen</th><th>Dates</th><th>État</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach($payments as $payment)
                        @php
                            $serviceTitle = $payment->order?->items?->first()?->service_title ?? 'Prestation de service';
                            $paymentStatus = $payment->status->value;
                        @endphp
                        <tr>
                            <td data-label="Références">
                                <strong>PAY-{{ str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT) }}</strong>
                                <span>{{ $payment->order?->order_number ?? '#'.$payment->order_id }}</span>
                                <small>{{ $serviceTitle }}</small>
                            </td>
                            <td data-label="Montant"><strong class="payment-table__amount">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</strong></td>
                            <td data-label="Moyen"><span>{{ $methodLabels[$payment->method->value] ?? $payment->method->value }}</span><small>Simulateur PROXIWORK</small></td>
                            <td data-label="Dates"><span>{{ optional($payment->created_at)->format('d/m/Y H:i') }}</span><small>Confirmation : {{ optional($payment->paid_at)->format('d/m/Y H:i') ?? 'non confirmée' }}</small></td>
                            <td data-label="État"><span class="status-badge status-badge--{{ $paymentStatus }}">{{ $statusLabels[$paymentStatus] ?? $paymentStatus }}</span></td>
                            <td data-label="Actions"><div class="payment-row-actions"><a class="btn btn-secondary btn-sm" href="{{ route('client.payments.show', $payment) }}">Détails</a>@if($payment->status === \App\Enums\PaymentStatus::SUCCEEDED)<a class="payment-text-link" href="{{ route('client.payments.receipt', $payment) }}">Reçu</a>@endif</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="client-pagination">{{ $payments->links() }}</div>
        @endif
    </section>
</div>
@endsection
