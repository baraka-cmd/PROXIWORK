@extends('layouts.admin')
@section('page_eyebrow','RBAC')
@section('page_title','Permissions')
@section('page_description','Catalogue des capacités utilisées par les rôles et les contrôles d’accès.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-lock"></i> Catalogue système</span>@endsection
@section('content')
<section class="admin-card">
<div class="admin-card__header"><div><span class="admin-card__eyebrow">Recherche</span><h3>Explorer les permissions</h3></div><a class="admin-link" href="{{ route('admin.permissions.index') }}">Réinitialiser</a></div>
<form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><div class="admin-input-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, libellé ou description"></div></label>
<label class="admin-field"><span>Groupe</span><select name="group"><option value="">Tous les groupes</option>@foreach($groups as $group)<option value="{{ $group }}" @selected(($filters['group'] ?? '') === $group)>{{ $group }}</option>@endforeach</select></label>
<div class="admin-filter-actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-filter"></i> Appliquer</button></div>
</form></section>
<section class="admin-card">
<div class="admin-card__header"><div><span class="admin-card__eyebrow">Gouvernance RBAC</span><h3>{{ $permissions->total() }} permission{{ $permissions->total()>1?'s':'' }}</h3></div><span class="admin-page-badge"><i class="fa-solid fa-shield-halved"></i> Lecture seule</span></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Permission</th><th>Groupe</th><th>Description</th><th>Rôles</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
@forelse($permissions as $permission)<tr><td><a class="admin-user-cell" href="{{ route('admin.permissions.show',$permission) }}"><span class="admin-avatar admin-avatar--role"><i class="fa-solid fa-key"></i></span><span><strong>{{ $permission->display_name }}</strong><small>{{ $permission->name }}</small></span></a></td><td><span class="admin-chip">{{ $permission->group }}</span></td><td><span class="admin-muted">{{ $permission->description ?: 'Aucune description.' }}</span></td><td>{{ $permission->roles_count }}</td><td class="admin-table__actions"><a class="admin-icon-button" href="{{ route('admin.permissions.show',$permission) }}" aria-label="Voir {{ $permission->display_name }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td></tr>
@empty<tr><td colspan="5"><div class="admin-empty"><i class="fa-solid fa-key"></i><strong>Aucune permission trouvée</strong><span>Modifiez vos critères.</span></div></td></tr>@endforelse
</tbody></table></div>
@if($permissions->hasPages())<div class="admin-pagination">{{ $permissions->links() }}</div>@endif
</section>
@endsection
