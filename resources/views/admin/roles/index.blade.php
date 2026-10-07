@extends('layouts.admin')

@section('page_eyebrow', 'RBAC')
@section('page_title', 'Rôles')
@section('page_description', 'Définissez précisément les responsabilités et permissions de chaque rôle.')

@section('page_actions')
    @can('create', App\Models\Role::class)
        <a class="button button--primary" href="{{ route('admin.roles.create') }}"><i class="fa-solid fa-plus"></i> Nouveau rôle</a>
    @endcan
@endsection

@section('content')
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>@endif
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Contrôle d’accès</span><h3>{{ $roles->total() }} rôle{{ $roles->total() > 1 ? 's' : '' }}</h3></div><span class="admin-page-badge"><i class="fa-solid fa-lock"></i> RBAC</span></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Rôle</th><th>Type</th><th>Utilisateurs</th><th>Permissions</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td><a class="admin-user-cell" href="{{ route('admin.roles.show', $role) }}"><span class="admin-avatar admin-avatar--role"><i class="fa-solid fa-user-shield"></i></span><span><strong>{{ $role->display_name }}</strong><small>{{ $role->name }}</small></span></a></td>
                        <td><span class="admin-status {{ $role->is_system ? 'admin-status--system' : 'admin-status--active' }}">{{ $role->is_system ? 'Système' : 'Personnalisé' }}</span></td>
                        <td>{{ $role->users_count }}</td><td>{{ $role->permissions_count }}</td>
                        <td class="admin-table__actions"><a class="admin-icon-button" href="{{ route('admin.roles.show', $role) }}" aria-label="Voir {{ $role->display_name }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                    </tr>
                @empty<tr><td colspan="5"><div class="admin-empty"><i class="fa-solid fa-user-shield"></i><strong>Aucun rôle</strong></div></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($roles->hasPages())<div class="admin-pagination">{{ $roles->links() }}</div>@endif
    </section>
@endsection
