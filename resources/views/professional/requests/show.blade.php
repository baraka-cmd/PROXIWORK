@extends('layouts.professional')

@section('title', 'Demande #' . $serviceRequest->getKey() . ' — PROXIWORK')
@section('page_heading', 'Détail de la demande')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Demande #{{ $serviceRequest->getKey() }}</p>
            <h1>{{ $serviceRequest->title }}</h1>
            <p class="page-lead">Reçue le {{ $serviceRequest->requested_at?->format('d/m/Y H:i') ?? $serviceRequest->created_at?->format('d/m/Y H:i') }}</p>
        </div>
        <div class="dashboard-page-header__actions">
            <a class="button button--ghost" href="{{ route('professional.requests.index') }}">Retour aux demandes</a>
            @if ($serviceRequest->status->value === 'requested' && ! $serviceRequest->quotation)
                <a class="button button--primary" href="{{ route('professional.quotes.create', $serviceRequest) }}">Créer un devis</a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif

    <div class="dashboard-content-grid">
        <section class="surface-card content-card">
            <h2>Description du besoin</h2>
            <p>{{ $serviceRequest->description }}</p>
            <dl class="details-list">
                <div><dt>Service</dt><dd>{{ $serviceRequest->service?->title ?? 'Non disponible' }}</dd></div>
                <div><dt>Budget minimum</dt><dd>{{ $serviceRequest->budget_min !== null ? number_format((float) $serviceRequest->budget_min, 2) . ' ' . ($serviceRequest->currency ?? '') : 'Non précisé' }}</dd></div>
                <div><dt>Budget maximum</dt><dd>{{ $serviceRequest->budget_max !== null ? number_format((float) $serviceRequest->budget_max, 2) . ' ' . ($serviceRequest->currency ?? '') : 'Non précisé' }}</dd></div>
                <div><dt>Date souhaitée</dt><dd>{{ $serviceRequest->desired_at?->format('d/m/Y H:i') ?? 'Flexible' }}</dd></div>
                <div><dt>Statut</dt><dd>{{ ucfirst($serviceRequest->status->value) }}</dd></div>
            </dl>
        </section>

        <aside class="surface-card content-card">
            <h2>Client</h2>
            <p>{{ $serviceRequest->client?->name ?? 'Client' }}</p>
            @if ($serviceRequest->address)
                <h3>Lieu de prestation</h3>
                <p>{{ $serviceRequest->address->city }}{{ $serviceRequest->address->province ? ', ' . $serviceRequest->address->province : '' }}</p>
                <p>{{ $serviceRequest->address->neighborhood }}</p>
            @endif
            @if ($serviceRequest->status->value === 'requested')
                <form method="POST" action="{{ route('professional.requests.reject', $serviceRequest) }}" data-action-form>
                    @csrf
                    <label for="reason" class="form-label">Motif du refus (facultatif)</label>
                    <textarea id="reason" name="reason" class="form-control" maxlength="500" rows="3">{{ old('reason') }}</textarea>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                    <button class="button button--danger" type="submit">Refuser la demande</button>
                </form>
            @endif
        </aside>
    </div>
@endsection
