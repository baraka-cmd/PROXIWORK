@extends('layouts.admin')
@section('page_eyebrow','RBAC')
@section('page_title',$permission->display_name)
@section('page_description','Détail de la permission et rôles auxquels elle est attribuée.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.permissions.index') }}"><i class="fa-solid fa-arrow-left"></i> Permissions</a>@endsection
@push('head')
    @vite(['resources/css/pages/admin/permissions-professionals.css', 'resources/js/pages/admin/permissions-professionals.js'])
@endpush

@section('content')
<div class="admin-detail-grid">
<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Identifiant</span><h3>{{ $permission->name }}</h3></div><span class="admin-chip">{{ $permission->group }}</span></div>
<dl class="admin-detail-list"><div><dt>Libellé</dt><dd>{{ $permission->display_name }}</dd></div><div><dt>Groupe</dt><dd>{{ $permission->group }}</dd></div><div><dt>Description</dt><dd>{{ $permission->description ?: 'Aucune description.' }}</dd></div><div><dt>Rôles</dt><dd>{{ $permission->roles->count() }}</dd></div></dl></section>
<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Attribution</span><h3>Rôles autorisés</h3></div></div><div class="admin-chip-list admin-chip-list--large">@forelse($permission->roles as $role)<a class="admin-chip" href="{{ route('admin.roles.show',$role) }}">{{ $role->display_name }}</a>@empty<span class="admin-muted">Aucun rôle.</span>@endforelse</div><div class="admin-note"><i class="fa-solid fa-circle-info"></i><span>L’attribution se fait depuis la gestion des rôles. Le catalogue reste gouverné par le RBAC.</span></div></section>
</div>
@endsection
