@extends('layouts.admin')
@section('title', 'Campagnes ciblées')
@section('content')
<div class="admin-page"><h1>Communication ciblée</h1><p>Les campagnes envoient des notifications internes aux comptes actifs et vérifiés. Les e-mails, SMS et push promotionnels ne sont pas activés par défaut.</p>
<section class="admin-card"><h2>Créer une campagne</h2><form method="POST" action="{{ route('admin.communication.campaigns.store') }}">@csrf
<label>Nom interne <input name="name" maxlength="160" required></label><label>Titre affiché <input name="subject" maxlength="180" required></label><label>Message <textarea name="body" maxlength="10000" rows="5" required></textarea></label>
<label>Audience <select name="audience"><option value="all">Clients et professionnels</option><option value="client">Clients uniquement</option><option value="professional">Professionnels uniquement</option></select></label><button>Enregistrer le brouillon</button></form></section>
<section class="admin-card"><h2>Historique des campagnes</h2><table class="admin-table"><thead><tr><th>Campagne</th><th>État</th><th>Destinataires</th><th>Envoyés</th><th>Échecs</th><th>Actions</th></tr></thead><tbody>
@forelse($campaigns as $campaign)<tr><td>{{ $campaign->name }}</td><td>{{ $campaign->status }}</td><td>{{ $campaign->recipient_count }}</td><td>{{ $campaign->sent_count }}</td><td>{{ $campaign->failed_count }}</td><td>@if($campaign->status === 'draft')<a href="{{ route('admin.communication.campaigns.preview',$campaign) }}">Prévisualiser</a> @if($campaign->approved_by)<form method="POST" action="{{ route('admin.communication.campaigns.dispatch',$campaign) }}">@csrf<button>Envoyer</button></form>@else<form method="POST" action="{{ route('admin.communication.campaigns.dispatch',$campaign) }}">@csrf<button>Envoyer (petite audience)</button></form><small>Au-delà de 100 destinataires, une approbation distincte est requise.</small>@endif @endif</td></tr>@empty<tr><td colspan="6">Aucune campagne.</td></tr>@endforelse
</tbody></table>{{ $campaigns->links() }}</section></div>
@endsection
