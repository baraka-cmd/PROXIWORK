@extends('layouts.admin')
@section('page_eyebrow','COMMUNICATION / DIFFUSION CIBLÉE')
@section('page_title','Campagnes ciblées')
@section('page_description','Audience calculée côté serveur à partir des rôles et critères autorisés. Les livraisons sont dédupliquées et traitées en file d’attente.')
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success">{{ session('success') }}</div>@endif
<section class="admin-card"><h3>Préparer une campagne</h3><form method="POST" action="{{ route('admin.communication.campaigns.store') }}" class="admin-form">@csrf
<label class="admin-field"><span>Titre</span><input name="title" required maxlength="180"></label><label class="admin-field"><span>Message</span><textarea name="body" required rows="4"></textarea></label>
<label class="admin-field"><span>Audience</span><select name="role"><option value="all">Tous les comptes actifs vérifiés</option><option value="client">Clients</option><option value="professional">Professionnels</option></select></label><label class="admin-field"><span>Comptes créés dans les N derniers jours (facultatif)</span><input type="number" name="recent_days" min="1" max="365"></label>
<fieldset><legend>Canaux</legend>@foreach(['mail'=>'Email','push'=>'Push','sms'=>'SMS'] as $v=>$label)<label><input type="checkbox" name="channels[]" value="{{ $v }}"> {{ $label }}</label>@endforeach</fieldset>
<label class="admin-field"><span>Priorité</span><select name="priority"><option>normal</option><option>important</option></select></label><label class="admin-field"><span>Programmer pour</span><input type="datetime-local" name="scheduled_at"></label>
<label><input type="checkbox" name="confirm_broadcast" value="1" required> Je confirme les critères d’audience et les canaux sélectionnés.</label><button class="button button--primary">Enregistrer la campagne</button></form></section>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Campagne</th><th>Audience estimée</th><th>Canaux</th><th>Statut</th><th>Notifications créées</th><th>Échecs</th><th></th></tr></thead><tbody>
@forelse($campaigns as $campaign)<tr><td>{{ $campaign->title }}</td><td>{{ number_format($campaign->audience_count,0,',',' ') }}</td><td>{{ implode(', ',$campaign->channels??[]) }}</td><td>{{ $campaign->status }}</td><td>{{ $campaign->created_count }}</td><td>{{ $campaign->failed_count }}</td><td>@if(in_array($campaign->status,['draft','scheduled']))<form method="POST" action="{{ route('admin.communication.campaigns.dispatch',$campaign) }}">@csrf<button class="button button--secondary button--sm">Diffuser maintenant</button></form>@endif</td></tr>@empty<tr><td colspan="7">Aucune campagne.</td></tr>@endforelse
</tbody></table></div>{{ $campaigns->links() }}</section>
@endsection