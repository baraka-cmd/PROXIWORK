@extends('layouts.admin')

@section('page_title', $skill ? 'Modifier une compétence' : 'Ajouter une compétence')
@section('page_description', 'Une compétence est proposée dans son domaine pour guider le choix des professionnels.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.skills.index') }}">Retour aux compétences</a>
@endsection

@section('content')
    <section class="admin-card">
        <form method="POST" action="{{ $skill ? route('admin.skills.update', $skill) : route('admin.skills.store') }}" class="admin-form">
            @csrf
            @if ($skill) @method('PUT') @endif

            <label class="admin-field"><span>Catégorie</span>
                <select name="category_id" required>
                    <option value="">Choisir une catégorie active</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $skill?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="admin-field"><span>Nom de la compétence</span><input name="name" value="{{ old('name', $skill?->name) }}" maxlength="100" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="admin-field"><span>Slug (facultatif)</span><input name="slug" value="{{ old('slug', $skill?->slug) }}" maxlength="120">@error('slug')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="admin-field"><span>Icône (classe ou identifiant visuel)</span><input name="icon" value="{{ old('icon', $skill?->icon) }}" maxlength="100">@error('icon')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="admin-field"><span>Description</span><textarea name="description" rows="3" maxlength="2000">{{ old('description', $skill?->description) }}</textarea>@error('description')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="admin-field"><span>État</span>
                <select name="status" required>
                    @foreach (\App\Enums\SkillStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $skill?->status?->value ?? 'active') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
                @error('status')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="admin-field"><span>Ordre d’affichage</span><input type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $skill?->sort_order ?? 0) }}" required>@error('sort_order')<small class="field-error">{{ $message }}</small>@enderror</label>

            <div class="form-actions"><button class="button button--primary" type="submit">{{ $skill ? 'Enregistrer la compétence' : 'Créer la compétence' }}</button></div>
        </form>
    </section>
@endsection
