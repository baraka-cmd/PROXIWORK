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
                            <td><a class="admin-icon-button" href="{{ route('admin.skills.edit', $skill) }}" aria-label="Modifier {{ $skill->name }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></a></td>
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
