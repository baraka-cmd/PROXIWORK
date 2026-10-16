@extends('layouts.client')

@section('title', 'Mes paiements')

@section('content')
@php
    $advancedFiltersActive = collect(['method', 'provider', 'currency', 'transaction_status', 'created_from', 'created_to', 'confirmed_from', 'confirmed_to'])
        ->contains(fn ($key) => filled($filters[$key] ?? null));
    $hasActiveFilters = collect($filters)->contains(fn ($value) => filled($value));
    $statusLabels['refunded'] = 'Remboursé (simulation)';
    $transactionStatusLabels['refunded'] = 'Remboursée (simulation)';
@endphp

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
        @if($errors->any())
            <div class="payment-error-summary" role="alert" tabindex="-1" data-error-summary>
                <h3>Vérifiez les critères de recherche</h3>
                <ul>
                    @foreach($errors->all() as $message)
                        <li><a href="#{{ $errors->has('created_from') ? 'created-from' : ($errors->has('created_to') ? 'created-to' : ($errors->has('confirmed_from') ? 'confirmed-from' : ($errors->has('confirmed_to') ? 'confirmed-to' : 'payment-search'))) }}">{{ $message }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="GET" action="{{ route('client.payments.index') }}" class="payment-filter-form">
            <label class="payment-filter-form__search">
                <span>Référence paiement, commande ou service</span>
                <input id="payment-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Ex. PAY-00000012, CMD-…" aria-describedby="payment-search-help">
                <small id="payment-search-help">La référence de tentative SIM-TXN n’est pas recherchée ici.</small>
            </label>
            <label><span>Statut du paiement</span>
                <select name="status">
                    <option value="">Tous les statuts</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <details class="payment-filter-advanced" @if($advancedFiltersActive || $errors->any()) open @endif>
                <summary>Critères avancés <span>moyen, devise, tentative et dates</span></summary>
                <div class="payment-filter-advanced__grid">
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
                    <label><span>Résultat de la tentative</span>
                        <select name="transaction_status">
                            <option value="">Tous les résultats</option>
                            @foreach($transactionStatusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['transaction_status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <fieldset class="payment-date-filter">
                        <legend>Date de création</legend>
                        <label><span>Du</span><input id="created-from" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}" @if($errors->has('created_from')) aria-invalid="true" aria-describedby="created-from-error" @endif></label>
                        <label><span>Au</span><input id="created-to" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}" @if($errors->has('created_to')) aria-invalid="true" aria-describedby="created-to-error" @endif></label>
                        @if($errors->has('created_from'))<p class="payment-field-error" id="created-from-error">{{ $errors->first('created_from') }}</p>@endif
                        @if($errors->has('created_to'))<p class="payment-field-error" id="created-to-error">{{ $errors->first('created_to') }}</p>@endif
                    </fieldset>
                    <fieldset class="payment-date-filter">
                        <legend>Date de confirmation</legend>
                        <label><span>Du</span><input id="confirmed-from" type="date" name="confirmed_from" value="{{ $filters['confirmed_from'] ?? '' }}" @if($errors->has('confirmed_from')) aria-invalid="true" aria-describedby="confirmed-from-error" @endif></label>
                        <label><span>Au</span><input id="confirmed-to" type="date" name="confirmed_to" value="{{ $filters['confirmed_to'] ?? '' }}" @if($errors->has('confirmed_to')) aria-invalid="true" aria-describedby="confirmed-to-error" @endif></label>
                        @if($errors->has('confirmed_from'))<p class="payment-field-error" id="confirmed-from-error">{{ $errors->first('confirmed_from') }}</p>@endif
                        @if($errors->has('confirmed_to'))<p class="payment-field-error" id="confirmed-to-error">{{ $errors->first('confirmed_to') }}</p>@endif
                    </fieldset>
                </div>
            </details>
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
                @if($hasActiveFilters)
                    <h3>Aucun paiement ne correspond à ces filtres</h3>
                    <p>Modifiez quelques critères ou réinitialisez la recherche pour retrouver vos opérations.</p>
                    <a class="btn btn-secondary" href="{{ route('client.payments.index') }}">Réinitialiser les filtres</a>
                @else
                    <h3>Aucun paiement n’a encore été initialisé</h3>
                    <p>Lorsqu’un paiement sera enregistré pour l’une de vos commandes, son état et son historique apparaîtront ici.</p>
                @endif
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
                            <td data-label="Montant" class="payment-table__amount-cell"><strong class="payment-table__amount">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</strong></td>
                            <td data-label="Moyen"><span>{{ $methodLabels[$payment->method->value] ?? $payment->method->value }}</span><small>Simulateur PROXIWORK</small></td>
                            <td data-label="Dates"><span>{{ optional($payment->created_at)->format('d/m/Y H:i') }}</span><small>Confirmation : {{ optional($payment->paid_at)->format('d/m/Y H:i') ?? 'non confirmée' }}</small></td>
                            <td data-label="État"><span class="status-badge status-badge--{{ $paymentStatus }}">{{ $statusLabels[$paymentStatus] ?? 'État à vérifier' }}</span>@if($paymentStatus === 'succeeded')<small>Simulation uniquement</small>@endif</td>
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
