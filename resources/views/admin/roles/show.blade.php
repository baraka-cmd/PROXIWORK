@extends('layouts.admin')

@section('page_eyebrow', 'RBAC')
@section('page_title', $role->display_name)
@section('page_description', $role->description ?: 'Aucune description définie.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.roles.index') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    @can('update', $role)<a class="button button--primary" href="{{ route('admin.roles.edit', $role) }}"><i class="fa-solid fa-pen"></i> Modifier</a>@endcan
@endsection

@section('content')
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>@endif
    <div class="admin-detail-grid">
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Rôle</span><h3>{{ $role->display_name }}</h3></div><span class="admin-status {{ $role->is_system ? 'admin-status--system' : 'admin-status--active' }}">{{ $role->is_system ? 'Système protégé' : 'Personnalisé' }}</span></div>
            <dl class="admin-definition-list"><div><dt>Nom technique</dt><dd><code>{{ $role->name }}</code></dd></div><div><dt>Utilisateurs</dt><dd>{{ $role->users_count }}</dd></div><div><dt>Permissions</dt><dd>{{ $role->permissions->count() }}</dd></div></dl>
        </section>
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Autorisation</span><h3>Permissions actives</h3></div></div>
            <div class="permission-list">@forelse($role->permissions as $permission)<div class="permission-list__item"><span class="permission-list__icon"><i class="fa-solid fa-key"></i></span><span><strong>{{ $permission->display_name }}</strong><small>{{ $permission->description }}</small><code>{{ $permission->name }}</code></span></div>@empty<p class="admin-muted">Aucune permission.</p>@endforelse</div>
        </section>
    </div>
    @can('delete', $role)
        <section class="admin-card admin-danger-zone"><div><span class="admin-card__eyebrow">Zone sensible</span><h3>Supprimer ce rôle</h3><p>Cette action détache le rôle de ses utilisateurs et de ses permissions. Les rôles système ne peuvent pas être supprimés.</p></div><form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Supprimer définitivement ce rôle ?">@csrf @method('DELETE')<button class="button button--danger" type="submit"><i class="fa-solid fa-trash"></i> Supprimer</button></form></section>
    @endcan
@endsection
