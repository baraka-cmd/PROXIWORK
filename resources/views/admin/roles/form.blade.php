@extends('layouts.admin')

@section('page_eyebrow', 'RBAC')
@section('page_title', $role ? 'Modifier le rôle' : 'Créer un rôle')
@section('page_description', $role ? 'Modifiez un rôle personnalisé et ses permissions.' : 'Créez un rôle personnalisé avec uniquement les permissions nécessaires.')

@section('page_actions')
    <a class="button button--secondary" href="{{ $role ? route('admin.roles.show', $role) : route('admin.roles.index') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
@endsection

@section('content')
    <form method="POST" action="{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="admin-role-form">
        @csrf
        @if($role) @method('PUT') @endif
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Identité</span><h3>Informations du rôle</h3></div></div>
            <div class="admin-form-grid">
                <label class="admin-field"><span>Nom technique</span><input name="name" required maxlength="100" value="{{ old('name', $role?->name) }}" placeholder="ex. content_manager"><small>Minuscules, chiffres, points, tirets et underscores.</small></label>
                <label class="admin-field"><span>Nom affiché</span><input name="display_name" required maxlength="150" value="{{ old('display_name', $role?->display_name) }}" placeholder="Gestionnaire de contenu"></label>
                <label class="admin-field admin-field--full"><span>Description</span><textarea name="description" rows="3" maxlength="1000" placeholder="Responsabilités de ce rôle">{{ old('description', $role?->description) }}</textarea></label>
            </div>
        </section>
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Autorisation</span><h3>Permissions</h3><p>Sélectionnez uniquement les capacités nécessaires.</p></div></div>
            @if($errors->any())<div class="admin-alert admin-alert--error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i><span>{{ $errors->first() }}</span></div>@endif
            <div class="permission-groups">
                @foreach($permissions as $group => $groupPermissions)
                    <fieldset class="permission-group">
                        <legend>{{ ucfirst(str_replace('_', ' ', $group)) }}</legend>
                        <div class="permission-grid">
                            @foreach($groupPermissions as $permission)
                                <label class="permission-item">
                                    <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids', $selectedPermissions), true))>
                                    <span><strong>{{ $permission->display_name }}</strong><small>{{ $permission->description }}</small><code>{{ $permission->name }}</code></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>
        <div class="admin-form-actions"><a class="button button--secondary" href="{{ $role ? route('admin.roles.show', $role) : route('admin.roles.index') }}">Annuler</a><button class="button button--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> {{ $role ? 'Enregistrer' : 'Créer le rôle' }}</button></div>
    </form>
@endsection
