@extends('layouts.professional')

@section('title', 'Documents professionnels — PROXIWORK')
@section('page_heading', 'Documents professionnels')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">DOSSIER DE VÉRIFICATION</p>
            <h1>Vos justificatifs</h1>
            <p class="page-lead">Transmettez uniquement les documents nécessaires à votre activité.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="surface-card">
        <div class="surface-card__header"><h2>Ajouter un document</h2></div>
        <div class="surface-card__body">
            <form method="POST" action="{{ route('professional.documents.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="form-label" for="document_type">Type de document</label>
                <select class="form-control form-select" id="document_type" name="document_type" required>
                    <option value="">Choisir un type</option>
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="file">Fichier</label>
                <input class="form-control" id="file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                <p class="form-hint">PDF ou image, 10 Mo maximum. Le fichier reste privé.</p>
                <button class="button button--primary" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i> Transmettre le document</button>
            </form>
        </div>
    </section>

    <section class="surface-card">
        <div class="surface-card__header"><h2>Documents transmis</h2></div>
        <div class="surface-card__body">
            @forelse ($documents as $document)
                <article class="list-row">
                    <div>
                        <strong>{{ $documentTypes[$document->document_type] ?? $document->document_type }}</strong>
                        <p>{{ $document->original_name }} · {{ number_format($document->size / 1024, 0) }} Ko</p>
                    </div>
                    <div>
                        <span class="badge badge--{{ $document->status === 'pending' ? 'warning' : ($document->status === 'approved' ? 'success' : 'danger') }}">{{ $document->status }}</span>
                        @if ($document->status !== 'approved')
                            <form method="POST" action="{{ route('professional.documents.destroy', $document) }}">
                                @csrf
                                @method('DELETE')
                                <button class="button button--ghost button--sm" type="submit">Supprimer</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="state-empty">Aucun document transmis pour le moment.</p>
            @endforelse
        </div>
    </section>
@endsection