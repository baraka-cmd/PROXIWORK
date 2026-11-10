@extends('layouts.admin')
@section('title', 'Historique de campagne')
@section('content')
<div class="admin-page"><h1>{{ $campaign->name }}</h1><p>{{ $campaign->subject }}</p><p>{{ $campaign->body }}</p>
<div class="admin-stats-grid"><article class="admin-stat-card"><span>Destinataires figés</span><strong>{{ number_format($campaign->recipient_count) }}</strong></article><article class="admin-stat-card"><span>Envoyés</span><strong>{{ number_format($campaign->sent_count) }}</strong></article><article class="admin-stat-card"><span>Échecs</span><strong>{{ number_format($campaign->failed_count) }}</strong></article></div>
<section class="admin-card"><h2>Résultat par destinataire</h2><table class="admin-table"><thead><tr><th>Compte</th><th>ID</th><th>État</th><th>Erreur technique</th><th>Traité le</th></tr></thead><tbody>
@forelse($recipients as $recipient)<tr><td>{{ $recipient->user?->name ?? 'Compte supprimé' }}</td><td>{{ $recipient->user_id }}</td><td>{{ $recipient->status }}</td><td>{{ $recipient->error_code ?? '—' }}</td><td>{{ $recipient->processed_at?->format('d/m/Y H:i:s') ?? '—' }}</td></tr>@empty<tr><td colspan="5">Aucun destinataire enregistré.</td></tr>@endforelse
</tbody></table>{{ $recipients->links() }}</section><a href="{{ route('admin.communication.campaigns.index') }}">Retour aux campagnes</a></div>
@endsection
