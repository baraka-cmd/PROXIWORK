@extends('layouts.professional')

@section('title', 'Documents professionnels — PROXIWORK')
@section('page_heading', 'Documents professionnels')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/documents.css', 'resources/js/pages/professional/documents.js'])
    @endunless
@endpush

@section('content')
    <div class="professional-documents">
        <div class="dashboard-page-header">
            <div>
                <p class="eyebrow">DOSSIER DE VÉRIFICATION</p>
                <h1>Vos justificatifs professionnels</h1>
                <p class="page-lead">Transmettez les documents nécessaires à votre activité et suivez leur état de vérification.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert--success" role="status" aria-live="polite">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert--error" role="alert" aria-live="assertive">
                <strong>Le document n’a pas pu être transmis.</strong>
                <p>{{ $errors->first() }}</p>
            </div>
        @endif

        <aside class="professional-documents__privacy" aria-label="Confidentialité des documents">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div>
                <strong>Votre dossier reste privé</strong>
                <p>Les justificatifs sont transmis à des fins de vérification. Ils ne sont pas des images de service et ne sont pas affichés dans votre profil public.</p>
            </div>
        </aside>

        <div class="professional-documents__layout">
            <section class="surface-card surface-card--lg" aria-labelledby="professional-document-upload-title">
                <div class="surface-card__header">
                    <div>
                        <h2 id="professional-document-upload-title">Transmettre un document</h2>
                        <p class="field-help">Formats acceptés : PDF, JPG, JPEG, PNG ou WebP. Taille maximale : 10 Mo.</p>
                    </div>
                </div>
                <div class="surface-card__body">
                    <form class="professional-documents__form" method="POST" action="{{ route('professional.documents.store') }}" enctype="multipart/form-data" data-professional-document-form>
                        @csrf
                        <div class="form-field">
                            <label class="form-label" for="document_type">Type de document <span class="form-label__required">*</span></label>
                            <select class="form-control form-select" id="document_type" name="document_type" required aria-describedby="document-type-help">
                                <option value="">Choisir un type</option>
                                @foreach ($documentTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small id="document-type-help" class="professional-documents__file-note">Choisissez la catégorie qui correspond au justificatif transmis.</small>
                            @error('document_type')<p class="form-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="form-field">
                            <label class="form-label" for="professional-document-file">Fichier <span class="form-label__required">*</span></label>
                            <input class="form-control" id="professional-document-file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required aria-describedby="document-file-help document-file-status">
                            <small id="document-file-help" class="professional-documents__file-note">Le type et la taille seront également vérifiés par le serveur.</small>
                            <p class="professional-documents__file-status" id="document-file-status" data-document-file-status aria-live="polite">Aucun fichier sélectionné.</p>
                            @error('file')<p class="form-error">{{ $message }}</p>@enderror
                        </div>

                        <button class="button button--primary" type="submit">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <span data-submit-label>Transmettre pour vérification</span>
                        </button>
                    </form>
                </div>
            </section>

            <section class="surface-card surface-card--lg" aria-labelledby="professional-document-list-title">
                <div class="surface-card__header">
                    <div>
                        <h2 id="professional-document-list-title">Documents transmis</h2>
                        <p class="field-help">{{ $documents->count() }} document(s) dans votre dossier.</p>
                    </div>
                </div>
                <div class="surface-card__body">
                    @if ($documents->isNotEmpty())
                        <div class="professional-documents__list">
                            @foreach ($documents as $document)
                                @php
                                    $documentStatus = (string) $document->status;
                                    $statusLabel = match ($documentStatus) {
                                        'pending' => 'En attente de vérification',
                                        'approved' => 'Approuvé',
                                        'rejected' => 'Refusé',
                                        default => 'État à confirmer',
                                    };
                                    $statusClass = match ($documentStatus) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'pending' => 'warning',
                                        default => 'neutral',
                                    };
                                    $statusHelp = match ($documentStatus) {
                                        'pending' => 'Votre document a été transmis et attend son examen.',
                                        'approved' => 'Ce document a été approuvé. Sa suppression n’est pas proposée depuis cet écran.',
                                        'rejected' => 'Ce document n’a pas été approuvé. Consultez les consignes communiquées par PROXIWORK si vous devez en transmettre un autre.',
                                        default => 'Le statut est en cours de clarification.',
                                    };
                                @endphp
                                <article class="professional-document-card">
                                    <span class="professional-document-card__icon" aria-hidden="true">
                                        <i class="fa-solid {{ str_contains(strtolower((string) $document->mime_type), 'pdf') ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                                    </span>
                                    <div class="professional-document-card__body">
                                        <h3>{{ $documentTypes[$document->document_type] ?? 'Autre justificatif' }}</h3>
                                        <p class="professional-document-card__filename">{{ $document->original_name }}</p>
                                        <div class="professional-document-card__meta">
                                            <span>{{ number_format($document->size / 1024, 0, ',', ' ') }} Ko</span>
                                            <span>Transmis le {{ $document->created_at?->locale(app()->getLocale())->translatedFormat('j F Y') ?? 'date non disponible' }}</span>
                                        </div>
                                        @if ($documentStatus !== 'approved')
                                            <div class="professional-document-card__actions">
                                                <form method="POST" action="{{ route('professional.documents.destroy', $document) }}" data-professional-document-form data-confirm-delete="true">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="button button--ghost button--sm" type="submit"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Supprimer le document</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="professional-document-card__status">
                                        <span class="badge badge--{{ $statusClass }}">{{ $statusLabel }}</span>
                                        <p>{{ $statusHelp }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="state-empty state-empty--compact">
                            <div>
                                <span class="state-empty__icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                                <h3>Votre dossier est encore vide</h3>
                                <p>Transmettez uniquement les justificatifs pertinents pour votre activité. Le statut de vérification sera mis à jour par le processus administratif.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
