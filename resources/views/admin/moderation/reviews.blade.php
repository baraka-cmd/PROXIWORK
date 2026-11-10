@extends('layouts.admin')
@section('page_eyebrow','ADMINISTRATION / MODÉRATION')
@section('page_title','Avis et évaluations')
@section('page_description','Examinez les avis publiés et masqués sans confondre une mauvaise note avec un abus.')
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>@endif
<section class="admin-stats-grid">
@foreach(['all'=>'Total','published'=>'Publiés','hidden'=>'Masqués','reported'=>'Signalements liés'] as $key=>$label)<div class="admin-stat-card"><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></div>@endforeach
</section>
<section class="admin-card"><form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Commentaire, client, professionnel, commande"></label>
<label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option><option value="published" @selected(($filters['status']??'')==='published')>Publié</option><option value="hidden" @selected(($filters['status']??'')==='hidden')>Masqué</option></select></label>
<label class="admin-field"><span>Note</span><select name="rating"><option value="">Toutes</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected((string)($filters['rating']??'')===(string)$i)>{{ $i }}/5</option>@endfor</select></label>
<label class="admin-field"><span>Signalement</span><select name="reported"><option value="">Tous</option><option value="1" @selected(($filters['reported']??'')==='1')>Signalés uniquement</option></select></label>
<div class="admin-filter-actions"><button class="button button--primary">Filtrer</button></div></form></section>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Avis</th><th>Note</th><th>Client</th><th>Professionnel</th><th>Commande</th><th>Statut</th><th>Action</th></tr></thead><tbody>
@forelse($reviews as $review)<tr><td>{{ \Illuminate\Support\Str::limit($review->comment ?: 'Sans commentaire',100) }}<small class="admin-table__muted">{{ $review->created_at?->format('d/m/Y H:i') }}</small></td><td>{{ $review->rating }}/5</td><td>{{ $review->client?->name }}</td><td>{{ $review->professional?->business_name ?: $review->professional?->user?->name }}</td><td>{{ $review->order?->order_number }}</td><td>{{ $review->status->value }}</td><td>
<form method="POST" action="{{ route('admin.moderation.reviews.update',$review) }}" class="admin-filter-grid">@csrf
<select name="status"><option value="published">Maintenir / republier</option><option value="hidden">Masquer</option></select>
<input name="reason" required minlength="10" maxlength="2000" placeholder="Justification obligatoire">
<button class="button button--secondary">Décider</button></form>
@if($review->response)<small>Réponse : {{ $review->response->status->value }}</small>
<form method="POST" action="{{ route('admin.moderation.responses.update',$review->response) }}">@csrf<select name="status"><option value="published">Publier</option><option value="hidden">Masquer</option></select><input name="reason" required minlength="10" maxlength="2000" placeholder="Justification"><button class="button button--secondary">Appliquer</button></form>@endif
</td></tr>
@empty<tr><td colspan="7"><div class="admin-empty"><strong>Aucun avis</strong><span>Aucun avis ne correspond aux filtres.</span></div></td></tr>@endforelse
</tbody></table></div>@if($reviews->hasPages())<div class="admin-pagination">{{ $reviews->links() }}</div>@endif</section>
@endsection
