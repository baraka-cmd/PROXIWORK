@extends('layouts.admin')
@section('page_eyebrow','PLATEFORME / CATALOGUE')
@section('page_title',$category->name)
@section('page_description','Dossier de la catégorie, de sa visibilité et de ses relations.')
@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.categories.edit', $category) }}">Modifier</a>
    @if($category->status->value !== 'archived')
        <form method="POST" action="{{ route('admin.categories.archive', $category) }}" data-confirm="Archiver cette catégorie ? Les services et compétences historiques seront conservés.">@csrf<button class="button button--danger" type="submit">Archiver</button></form>
    @endif
@endsection
@section('content')
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Informations générales</span><h3>{{ $category->name }}</h3></div><span class="admin-status admin-status--{{ $category->status->value }}">{{ $category->status->value }}</span></div>
        <div class="admin-detail-list">
            <div><span>Slug</span><strong>{{ $category->slug }}</strong></div>
            <div><span>Type</span><strong>{{ $category->parent_id ? 'Sous-catégorie' : 'Catégorie principale' }}</strong></div>
            <div><span>Parent</span><strong>{{ $category->parent?->name ?? '—' }}</strong></div>
            <div><span>Icône</span><strong>{{ $category->icon ?: '—' }}</strong></div>
            <div><span>Image / Cloudinary</span><strong>{{ $category->image_path ?: '—' }}</strong></div>
            <div><span>Mise en avant</span><strong>{{ $category->is_featured ? 'Oui' : 'Non' }}</strong></div>
            <div><span>Ordre</span><strong>{{ $category->sort_order }}</strong></div>
            <div><span>Services directs</span><strong>{{ $category->services_count }}</strong></div>
            <div><span>Services des sous-catégories</span><strong>{{ $childServiceCount }}</strong></div>
            <div><span>Compétences directes</span><strong>{{ $category->skills_count }}</strong></div>
            <div><span>Compétences des sous-catégories</span><strong>{{ $childSkillCount }}</strong></div>
        </div>
        @if($category->description)<p>{{ $category->description }}</p>@endif
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><h3>Sous-catégories</h3><span class="admin-muted">{{ $category->children->count() }}</span></div>
        @forelse($category->children as $child)
            <div class="admin-list-row">
                <div><a href="{{ route('admin.categories.show', $child) }}"><strong>{{ $child->name }}</strong></a><small>{{ $child->slug }} · {{ $child->services_count }} services · {{ $child->skills_count }} compétences</small></div>
                <span class="admin-status admin-status--{{ $child->status->value }}">{{ $child->status->value }}</span>
            </div>
        @empty
            <div class="admin-empty"><strong>Aucune sous-catégorie</strong><span>Ajoutez-en une pour structurer ce domaine.</span></div>
        @endforelse
    </section>
@endsection
