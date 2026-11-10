@extends('layouts.admin')

@section('page_title', 'Compétences')
@section('page_description', 'Associez les compétences aux catégories pour structurer le parcours des professionnels.')

@section('page_actions')
    <a class="button button--primary" href="{{ route('admin.skills.create') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter une compétence</a>
@endsection

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>
    @endif

    <section class="admin-card">
        <form method="GET" class="admin-filter-grid">
            <label class="admin-field admin-field--wide"><span>Recherche</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou slug"></label>
            <label class="admin-field"><span>Catégorie</span><select name="category_id"><option value="">Toutes</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category_id'] ?? '') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(['active'=>'Active','inactive'=>'Inactive','archived'=>'Archivée'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="admin-filter-actions"><button class="button button--primary" type="submit">Filtrer</button><a class="button button--secondary" href="{{ route('admin.skills.index') }}">Réinitialiser</a></div>
        </form>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Compétence</th><th>Catégorie</th><th>État</th><th>Profils</th><th>Services</th><th><span class="sr-only">Action</span></th></tr></thead>
                <tbody>
                    @forelse ($skills as $skill)
                        <tr>
                            <td><strong>{{ $skill->name }}</strong><small>{{ $skill->slug }}</small></td>
                            <td>{{ $skill->category?->name ?? 'À classer' }}</td>
                            <td><span class="admin-status admin-status--{{ $skill->status->value }}">{{ $skill->status->value }}</span></td>
                            <td>{{ $skill->professional_profiles_count }}</td>
                            <td>{{ $skill->services_count }}</td>
                            <td class="admin-table__actions">
                                @if(auth()->user()->hasPermissionTo('skills.manage'))
                                    <a class="admin-icon-button" href="{{ route('admin.skills.edit', $skill) }}" aria-label="Modifier {{ $skill->name }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                    @if($skill->status->value !== 'archived')
                                        <form method="POST" action="{{ route('admin.skills.archive', $skill) }}" data-confirm="Archiver cette compétence ? Les associations historiques seront conservées.">@csrf<button class="admin-icon-button" type="submit" aria-label="Archiver {{ $skill->name }}"><i class="fa-solid fa-box-archive" aria-hidden="true"></i></button></form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="admin-empty"><strong>Aucune compétence enregistrée.</strong><span>Ajoutez les compétences proposées aux professionnels.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $skills->links() }}</div>
    </section>
@endsection
