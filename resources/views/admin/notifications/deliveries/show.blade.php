@extends('layouts.admin')

@section('title', 'Détail d’une livraison de notification')

@section('content')
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1>Livraison #{{ $delivery->id }}</h1>
            <p>Traçabilité technique d’une notification, sans modification de son événement métier.</p>
        </div>
        <a href="{{ route('admin.notifications.deliveries.index') }}">Retour à l’historique</a>
    </div>

    @if($delivery->status === 'FAILED' && $delivery->attempts < 5 && $retryable && auth()->user()?->hasPermissionTo('admin.notifications.retry'))
        <section class="admin-card"><h2>Relancer la livraison</h2><p>Une nouvelle tentative est possible car le résultat est connu comme échoué. Les états incertains ne sont jamais relancés automatiquement.</p><form method="POST" action="{{ route('admin.notifications.deliveries.retry', $delivery) }}">@csrf<button type="submit">Relancer une tentative</button></form></section>
    @endif
    <section class="admin-card">
        <dl class="admin-detail-list">
            <dt>Destinataire</dt><dd>{{ $delivery->user?->name ?? 'Utilisateur supprimé' }} (ID {{ $delivery->user_id }})</dd>
            <dt>Destination masquée</dt><dd>{{ $delivery->recipient_hint ?: 'Non renseignée' }}</dd>
            <dt>Canal</dt><dd>{{ strtoupper($delivery->channel) }}</dd>
            <dt>État technique</dt><dd>{{ $delivery->status }}</dd>
            <dt>Tentatives</dt><dd>{{ $delivery->attempts }}</dd>
            <dt>Créée le</dt><dd>{{ $delivery->created_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
            <dt>Mise en file</dt><dd>{{ $delivery->queued_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
            <dt>Début de traitement</dt><dd>{{ $delivery->processing_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
            <dt>Acceptée par le canal</dt><dd>{{ $delivery->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
            <dt>Remise confirmée</dt><dd>{{ $delivery->delivered_at?->format('d/m/Y H:i:s') ?? 'Non confirmée' }}</dd>
            <dt>Référence fournisseur</dt><dd>{{ $delivery->provider_reference ?: '—' }}</dd>
            <dt>Code de dernière erreur</dt><dd>{{ $delivery->last_error_code ?: '—' }}</dd>
        </dl>
    </section>

    <section class="admin-card">
        <h2>Historique des tentatives</h2>
        <div class="admin-table-container"><table class="admin-table"><thead><tr><th>Tentative</th><th>Canal</th><th>État</th><th>Début</th><th>Fin</th><th>Référence fournisseur</th><th>Erreur</th></tr></thead><tbody>
        @forelse($delivery->attempts as $attempt)<tr><td>#{{ $attempt->attempt_number }}</td><td>{{ strtoupper($attempt->channel) }}</td><td>{{ $attempt->status }}</td><td>{{ $attempt->started_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $attempt->finished_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $attempt->provider_reference ?? '—' }}</td><td>{{ $attempt->error_code ?? '—' }}</td></tr>@empty<tr><td colspan="7">Aucune tentative enregistrée.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    @if ($delivery->notification)
        <section class="admin-card">
            <h2>Notification interne associée</h2>
            <p><strong>Titre :</strong> {{ $delivery->notification->data['title'] ?? 'Sans titre' }}</p>
            <p><strong>Contenu :</strong> {{ $delivery->notification->data['message'] ?? $delivery->notification->data['body'] ?? 'Contenu non disponible' }}</p>
            <p><strong>Créée le :</strong> {{ $delivery->notification->created_at?->format('d/m/Y H:i:s') ?? '—' }}</p>
            <p><strong>Lue :</strong> {{ $delivery->notification->read_at?->format('d/m/Y H:i:s') ?? 'Non lue' }}</p>
        </section>
    @endif
</div>
@endsection
