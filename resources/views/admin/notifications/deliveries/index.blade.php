@extends('layouts.admin')

@section('title', 'Notifications et historique des livraisons')

@section('content')
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1>Notifications et livraisons</h1>
            <p>Supervision des notifications générées et du résultat connu de chaque canal externe.</p>
        </div>
    </div>

    <div class="admin-stats-grid">
        <article class="admin-stat-card"><span>Total des livraisons</span><strong>{{ number_format($summary['total']) }}</strong></article>
        <article class="admin-stat-card"><span>En attente</span><strong>{{ number_format($summary['pending']) }}</strong></article>
        <article class="admin-stat-card"><span>En traitement</span><strong>{{ number_format($summary['processing']) }}</strong></article>
        <article class="admin-stat-card"><span>Envoyées / remises</span><strong>{{ number_format($summary['sent']) }}</strong></article>
        <article class="admin-stat-card"><span>À examiner</span><strong>{{ number_format($summary['attention']) }}</strong></article>
    </div>

    <form method="GET" action="{{ route('admin.notifications.deliveries.index') }}" class="admin-filter-form">
        <label>Recherche
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Destinataire, ID utilisateur ou livraison">
        </label>
        <label>Canal
            <select name="channel">
                <option value="">Tous les canaux</option>
                @foreach (['mail' => 'E-mail', 'sms' => 'SMS', 'push' => 'Push'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['channel'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>État
            <select name="status">
                <option value="">Tous les états</option>
                @foreach (['PENDING', 'RETRYING', 'PROCESSING', 'SENT', 'DELIVERED', 'FAILED', 'UNKNOWN'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit">Filtrer</button>
        <a href="{{ route('admin.notifications.deliveries.index') }}">Réinitialiser</a>
    </form>

    <div class="admin-table-container">
        <table class="admin-table">
            <thead><tr><th>Livraison</th><th>Destinataire</th><th>Canal</th><th>État</th><th>Tentatives</th><th>Créée le</th><th></th></tr></thead>
            <tbody>
            @forelse ($deliveries as $delivery)
                <tr>
                    <td>#{{ $delivery->id }}</td>
                    <td>
                        {{ $delivery->user?->name ?? 'Utilisateur supprimé' }}
                        <small>{{ $delivery->recipient_hint ?: 'Destination non renseignée' }}</small>
                    </td>
                    <td>{{ strtoupper($delivery->channel) }}</td>
                    <td>{{ $delivery->status }}</td>
                    <td>{{ $delivery->attempts }}</td>
                    <td>{{ $delivery->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td><a href="{{ route('admin.notifications.deliveries.show', $delivery) }}">Détails</a></td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune livraison ne correspond aux filtres.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $deliveries->links() }}
    <p class="admin-help-text">« Envoyée » signifie que le canal a accepté l’envoi. La remise effective et la lecture ne sont confirmées que si le fournisseur transmet cette information.</p>
</div>
@endsection
