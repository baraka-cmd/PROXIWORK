@extends('layouts.admin')

@section('page_eyebrow','ADMINISTRATION / CONTRÔLE')
@section('page_title','Vérification professionnelle')
@section('page_description','Centralisez la file de vérification des professionnels et ouvrez leur dossier avant toute décision.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-user-check"></i> Verification Center</span>@endsection

@section('content')
<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">File de vérification</span><h3>{{ $professionals->total() }} dossier{{ $professionals->total() > 1 ? 's' : '' }}</h3></div></div>
<form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, e-mail ou titre"></label>
<label class="admin-field"><span>Statut</span><select name="verification_status"><option value="">Tous</option>@foreach(['pending'=>'En attente','under_review'=>'En revue','verified'=>'Vérifié','rejected'=>'Rejeté'] as $v=>$l)<option value="{{ $v }}" @selected(($filters['verification_status'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></label>
<label class="admin-field"><span>Compte</span><select name="account_status"><option value="">Tous</option><option value="active">Actif</option><option value="suspended">Suspendu</option></select></label>
<div class="admin-filter-actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button></div>
</form></section>

<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Professionnel</th><th>Statut</th><th>Compte</th><th>Création</th><th></th></tr></thead><tbody>
@forelse($professionals as $professional)<tr><td><a class="admin-user-cell" href="{{ route('admin.verification.show',$professional) }}"><span class="admin-avatar">{{ mb_strtoupper(mb_substr($professional->user->name ?? '?',0,1)) }}</span><span><strong>{{ $professional->user->name }}</strong><small>{{ $professional->professional_title ?: $professional->user->email }}</small></span></a></td><td><span class="admin-status admin-status--{{ $professional->verification_status?->value }}">{{ ['pending'=>'En attente','under_review'=>'En revue','verified'=>'Vérifié','rejected'=>'Rejeté'][$professional->verification_status?->value] ?? '—' }}</span></td><td>{{ $professional->user->account_status?->value }}</td><td>{{ $professional->created_at?->format('d/m/Y') }}</td><td><a class="admin-icon-button" href="{{ route('admin.verification.show',$professional) }}" aria-label="Ouvrir le dossier"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td></tr>
@empty<tr><td colspan="5"><div class="admin-empty"><i class="fa-solid fa-user-check"></i><strong>Aucun dossier</strong><span>Aucun professionnel ne correspond aux critères.</span></div></td></tr>@endforelse
</tbody></table></div>@if($professionals->hasPages())<div class="admin-pagination">{{ $professionals->links() }}</div>@endif</section>
@endsection