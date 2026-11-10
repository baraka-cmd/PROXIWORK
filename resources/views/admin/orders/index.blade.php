@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/orders.css')
    @endunless
@endpush

@section('page_title', 'Commandes')
@section('page_description', 'Centre de supervision commerciale et opérationnelle des commandes PROXIWORK.')

@section('content')
@php
    $statusLabels = [
        'pending_payment' => 'En attente de paiement',
        'confirmed' => 'Confirmée',
        'in_progress' => 'En cours',
        'delivery_submitted' => 'Livrable soumis',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'disputed' => 'Contestée',
    ];
@endphp

<section class="admin-order-kpis" aria-label="Indicateurs des commandes">
    @foreach([
        ['label' => 'En attente de paiement', 'value' => $overview['pending_payment'] ?? 0],
        ['label' => 'Confirmées', 'value' => $overview['confirmed'] ?? 0],
        ['label' => 'En cours', 'value' => $overview['in_progress'] ?? 0],
        ['label' => 'Livrables à examiner', 'value' => $overview['delivery_submitted'] ?? 0],
        ['label' => 'Terminées', 'value' => $overview['completed'] ?? 0],
        ['label' => 'Annulées', 'value' => $overview['cancelled'] ?? 0],
        ['label' => 'Contestées', 'value' => $overview['disputed'] ?? 0],
        ['label' => 'Commandes avec blocage signalé', 'value' => $overview['work_blocked'] ?? 0],
    ] as $kpi)
        <article class="admin-card admin-order-kpi">
            <span>{{ $kpi['label'] }}</span>
            <strong>{{ number_format($kpi['value']) }}</strong>
        </article>
    @endforeach
</section>

@if(($overview['payment_succeeded_not_started'] ?? 0) > 0)
    <section class="admin-order-alert" role="status">
        <strong>{{ $overview['payment_succeeded_not_started'] }} commande(s) confirmée(s) et payée(s) sans démarrage enregistré.</strong>
        <span>À examiner selon le planning convenu ; cet indicateur n'affirme pas à lui seul un retard contractuel.</span>
    </section>
@endif

<section class="admin-card">
    <div class="admin-card__header">
        <h3>Recherche et filtres</h3>
        <a class="admin-link" href="{{ route('admin.orders.index') }}">Réinitialiser</a>
    </div>
    <form method="GET" class="admin-filter-grid">
        <label class="admin-field admin-field--wide">
            <span>Recherche globale</span>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence, client ou professionnel (nom / e-mail)">
        </label>
        <label class="admin-field">
            <span>Statut de commande</span>
            <select name="status">
                <option value="">Tous les statuts</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] ?? $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>Statut du paiement</span>
            <select name="payment_status">
                <option value="">Tous les paiements</option>
                @foreach(['initiated','pending','processing','succeeded','failed','cancelled','expired','refunded'] as $status)
                    <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>ID du service</span>
            <input type="number" min="1" name="service_id" value="{{ $filters['service_id'] ?? '' }}" placeholder="Tous les services">
        </label>
        <label class="admin-field">
            <span>ID de catégorie</span>
            <input type="number" min="1" name="category_id" value="{{ $filters['category_id'] ?? '' }}" placeholder="Toutes les catégories">
        </label>
        <label class="admin-field">
            <span>Incident opérationnel</span>
            <select name="has_incident">
                <option value="">Tous</option>
                <option value="1" @selected(($filters['has_incident'] ?? '') === '1')>Avec blocage signalé</option>
                <option value="0" @selected(($filters['has_incident'] ?? '') === '0')>Sans blocage signalé</option>
            </select>
        </label>
        <label class="admin-field">
            <span>Trier par</span>
            <select name="sort">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récentes</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Plus anciennes</option>
                <option value="amount_desc" @selected(($filters['sort'] ?? '') === 'amount_desc')>Montant décroissant</option>
                <option value="amount_asc" @selected(($filters['sort'] ?? '') === 'amount_asc')>Montant croissant</option>
            </select>
        </label>
        <label class="admin-field"><span>Créée du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="admin-field"><span>Créée au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <div class="admin-filter-actions"><button class="button button--primary" type="submit">Appliquer les filtres</button></div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-card__header">
        <h3>Registre des commandes</h3>
        <span>{{ $orders->total() }} résultat(s)</span>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Référence / création</th><th>Client</th><th>Professionnel</th><th>Service</th><th>Commande</th><th>Montant</th><th>Paiement</th><th>Signalement</th><th></th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $order) }}"><strong>{{ $order->order_number }}</strong></a><small>{{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</small></td>
                    <td>{{ $order->client?->name ?? '—' }}<small>{{ $order->client?->email ?? '' }}</small></td>
                    <td>{{ $order->professional?->user?->name ?? '—' }}<small>{{ $order->professional?->user?->email ?? '' }}</small></td>
                    <td>{{ $order->serviceRequest?->service?->title ?? $order->serviceRequest?->title ?? '—' }}</td>
                    <td><span class="admin-status admin-status--{{ $order->status->value }}">{{ $statusLabels[$order->status->value] ?? $order->status->value }}</span></td>
                    <td>{{ number_format((float) $order->total, 2) }} {{ $order->currency }}</td>
                    <td>{{ $order->payment?->status?->value ?? 'Aucun paiement' }}</td>
                    <td>{{ ($order->has_work_blocker ?? false) ? 'Signalement enregistré' : '—' }}</td>
                    <td><a class="admin-icon-button" href="{{ route('admin.orders.show', $order) }}" aria-label="Consulter le dossier {{ $order->order_number }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="admin-empty"><strong>Aucune commande ne correspond à ces critères.</strong></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div class="admin-pagination">{{ $orders->links() }}</div>@endif
</section>
@endsection
