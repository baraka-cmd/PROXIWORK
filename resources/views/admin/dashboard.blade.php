@extends('layouts.admin')

@section('page_eyebrow', 'PILOTAGE DE LA PLATEFORME')
@section('page_title', 'Dashboard')
@section('page_description', 'Une vue opérationnelle de l’activité de PROXIWORK sur la période sélectionnée.')

@section('page_actions')
    <form class="admin-dashboard-period" method="GET" action="{{ route('admin.dashboard') }}">
        <label>
            <span class="sr-only">Du</span>
            <input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Date de début">
        </label>
        <span aria-hidden="true">→</span>
        <label>
            <span class="sr-only">Au</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Date de fin">
        </label>
        <button class="button button--primary button--small" type="submit">
            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
            Actualiser
        </button>
    </form>
@endsection

@section('content')
    <div class="admin-dashboard">
        <section class="admin-dashboard__kpis" aria-label="Indicateurs principaux">
            @php
                $kpis = [
                    ['label' => 'Utilisateurs', 'value' => $dashboard['users']['total'], 'new' => $dashboard['users']['new'], 'icon' => 'fa-users'],
                    ['label' => 'Professionnels', 'value' => $dashboard['professionals']['total'], 'new' => $dashboard['professionals']['new'], 'icon' => 'fa-user-tie'],
                    ['label' => 'Clients', 'value' => $dashboard['clients']['total'], 'new' => $dashboard['clients']['new'], 'icon' => 'fa-user-group'],
                    ['label' => 'Vérifications à traiter', 'value' => $dashboard['professionals']['verification']['pending'] + $dashboard['professionals']['verification']['under_review'], 'new' => null, 'icon' => 'fa-user-check'],
                ];
            @endphp

            @foreach ($kpis as $kpi)
                <article class="admin-dashboard__kpi">
                    <div class="admin-dashboard__kpi-icon"><i class="fa-solid {{ $kpi['icon'] }}" aria-hidden="true"></i></div>
                    <div>
                        <span>{{ $kpi['label'] }}</span>
                        <strong>{{ number_format($kpi['value']) }}</strong>
                        @if ($kpi['new'] !== null)
                            <small>+{{ number_format($kpi['new']) }} sur la période</small>
                        @else
                            <small>en attente ou en examen</small>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <div class="admin-dashboard__grid">
            <section class="admin-dashboard__card admin-dashboard__card--wide">
                <div class="admin-dashboard__card-header">
                    <div>
                        <span class="eyebrow">VÉRIFICATION</span>
                        <h2>Professionnels</h2>
                    </div>
                    <a class="button button--secondary button--small" href="{{ url('/admin/verification') }}">Voir</a>
                </div>
                <div class="admin-dashboard__status-grid">
                    @foreach ([
                        ['label' => 'En attente', 'key' => 'pending'],
                        ['label' => 'En examen', 'key' => 'under_review'],
                        ['label' => 'Vérifiés', 'key' => 'verified'],
                        ['label' => 'Refusés', 'key' => 'rejected'],
                    ] as $item)
                        <div class="admin-dashboard__status">
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ number_format($dashboard['professionals']['verification'][$item['key']]) }}</strong>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="admin-dashboard__card">
                <div class="admin-dashboard__card-header">
                    <div>
                        <span class="eyebrow">SERVICES</span>
                        <h2>Catalogue</h2>
                    </div>
                    <a href="{{ url('/admin/services') }}" aria-label="Voir les services"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </div>
                <div class="admin-dashboard__status-list">
                    @foreach ($dashboard['services'] as $status => $count)
                        <div><span>{{ str_replace('_', ' ', ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>
                    @endforeach
                </div>
            </section>

            <section class="admin-dashboard__card">
                <div class="admin-dashboard__card-header">
                    <div>
                        <span class="eyebrow">DEMANDES</span>
                        <h2>Service requests</h2>
                    </div>
                    <a href="{{ url('/admin/orders') }}" aria-label="Voir les opérations"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </div>
                <div class="admin-dashboard__status-list">
                    @foreach ($dashboard['requests'] as $status => $count)
                        <div><span>{{ str_replace('_', ' ', ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>
                    @endforeach
                </div>
            </section>

            <section class="admin-dashboard__card admin-dashboard__card--wide">
                <div class="admin-dashboard__card-header">
                    <div>
                        <span class="eyebrow">COMMANDES</span>
                        <h2>Cycle transactionnel</h2>
                    </div>
                    <a href="{{ url('/admin/orders') }}" class="button button--secondary button--small">Commandes</a>
                </div>
                <div class="admin-dashboard__status-grid">
                    @forelse ($dashboard['orders'] as $status => $count)
                        <div class="admin-dashboard__status">
                            <span>{{ str_replace('_', ' ', ucfirst($status)) }}</span>
                            <strong>{{ number_format($count) }}</strong>
                        </div>
                    @empty
                        <p class="admin-dashboard__empty">Aucune commande sur la période.</p>
                    @endforelse
                </div>
            </section>

            <section class="admin-dashboard__card admin-dashboard__card--wide">
                <div class="admin-dashboard__card-header">
                    <div>
                        <span class="eyebrow">FINANCES</span>
                        <h2>Volumes et commissions</h2>
                    </div>
                    <a href="{{ url('/admin/payments') }}" class="button button--secondary button--small">Paiements</a>
                </div>

                @forelse ($dashboard['financial']['payment_volume'] as $currency => $volume)
                    @php $commission = $dashboard['financial']['commissions'][$currency] ?? null; @endphp
                    <div class="admin-dashboard__finance">
                        <strong>{{ $currency }}</strong>
                        <div><span>Volume paiements</span><b>{{ number_format((float) $volume, 2) }} {{ $currency }}</b></div>
                        @if ($commission)
                            <div><span>Commission plateforme</span><b>{{ number_format((float) $commission['commission'], 2) }} {{ $currency }}</b></div>
                            <div><span>Net professionnels</span><b>{{ number_format((float) $commission['professional_net'], 2) }} {{ $currency }}</b></div>
                        @endif
                    </div>
                @empty
                    <p class="admin-dashboard__empty">Aucun paiement réussi sur la période.</p>
                @endforelse
            </section>

            <section class="admin-dashboard__card">
                <div class="admin-dashboard__card-header">
                    <div>
                        <span class="eyebrow">PAIEMENTS</span>
                        <h2>Transactions</h2>
                    </div>
                    <a href="{{ url('/admin/payments') }}" aria-label="Voir les paiements"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </div>
                <div class="admin-dashboard__status-list">
                    @foreach ($dashboard['payments'] as $status => $count)
                        <div><span>{{ str_replace('_', ' ', ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
