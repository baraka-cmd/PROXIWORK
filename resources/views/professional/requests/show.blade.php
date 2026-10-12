@extends('layouts.professional')

@section('title', 'Demande PW-REQ-' . str_pad((string) $serviceRequest->getKey(), 6, '0', STR_PAD_LEFT) . ' — PROXIWORK')
@section('page_heading', 'Détail de la demande')

@push('head')
    @vite('resources/css/pages/client/service-requests.css')
@endpush

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Demande PW-REQ-{{ str_pad((string) $serviceRequest->getKey(), 6, '0', STR_PAD_LEFT) }}</p>
            <h1>{{ $serviceRequest->title }}</h1>
            <p class="page-lead">Reçue le {{ $serviceRequest->requested_at?->format('d/m/Y H:i') ?? $serviceRequest->created_at?->format('d/m/Y H:i') }}</p>
            <span class="request-status-badge request-status-badge--{{ $serviceRequest->status->value }}">{{ $serviceRequest->status->label() }}</span>
        </div>
        <div class="dashboard-page-header__actions">
            <a class="button button--ghost" href="{{ route('professional.requests.index') }}">Retour aux demandes</a>
            @if($conversation)
                <a class="button button--ghost" href="{{ route('professional.messages.show', $conversation) }}">Ouvrir la conversation</a>
            @else
                <a class="button button--ghost" href="{{ route('professional.messages') }}">Ouvrir la messagerie</a>
            @endif
            @if ($serviceRequest->status->value === 'requested' && ! $serviceRequest->quotation)
                <a class="button button--primary" href="{{ route('professional.quotes.create', $serviceRequest) }}">Créer un devis</a>
            @endif
            @if($serviceRequest->quotation)
                <a class="button button--primary" href="{{ route('professional.quotes.show', $serviceRequest->quotation) }}">Voir le devis</a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert--error" role="alert">
            <strong>La demande n’a pas pu être traitée.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="dashboard-content-grid">
        <section class="surface-card content-card">
            <h2>Description du besoin</h2>
            <p>{{ $serviceRequest->description }}</p>
            <dl class="details-list">
                <div><dt>Service</dt><dd>{{ $serviceRequest->service?->title ?? 'Non disponible' }}</dd></div>
                <div><dt>Budget minimum</dt><dd>{{ $serviceRequest->budget_min !== null ? number_format((float) $serviceRequest->budget_min, 2, ',', ' ') . ' ' . ($serviceRequest->currency ?? '') : 'Non précisé' }}</dd></div>
                <div><dt>Budget maximum</dt><dd>{{ $serviceRequest->budget_max !== null ? number_format((float) $serviceRequest->budget_max, 2, ',', ' ') . ' ' . ($serviceRequest->currency ?? '') : 'Non précisé' }}</dd></div>
                <div><dt>Date souhaitée</dt><dd>{{ $serviceRequest->desired_at?->format('d/m/Y H:i') ?? 'Flexible / à convenir' }}</dd></div>
                <div><dt>Statut</dt><dd>{{ $serviceRequest->status->label() }}</dd></div>
            </dl>
        </section>

        <aside class="surface-card content-card">
            <h2>Client</h2>
            <p>{{ $serviceRequest->client?->name ?? 'Client' }}</p>
            @if ($serviceRequest->address)
                <h3>Lieu de prestation</h3>
                <p>{{ $serviceRequest->address->address_line_1 }}</p>
                @if($serviceRequest->address->address_line_2)
                    <p>{{ $serviceRequest->address->address_line_2 }}</p>
                @endif
                <p>{{ $serviceRequest->address->neighborhood }}{{ $serviceRequest->address->city ? ', '.$serviceRequest->address->city : '' }}{{ $serviceRequest->address->province ? ', '.$serviceRequest->address->province : '' }}</p>
            @endif
            @if ($serviceRequest->status->value === 'requested')
                <form method="POST" action="{{ route('professional.requests.reject', $serviceRequest) }}" data-action-form>
                    @csrf
                    <label for="reason" class="form-label">Motif du refus (facultatif)</label>
                    <textarea id="reason" name="reason" class="form-control" maxlength="500" rows="3">{{ old('reason') }}</textarea>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                    <button class="button button--danger" type="submit" data-confirm-submit="Refuser cette demande ? Cette action ne pourra pas être annulée." data-loading-label="Refus…">Refuser la demande</button>
                </form>
            @endif
        </aside>
    </div>

    <section class="surface-card content-card">
        <h2>Historique de la demande</h2>
        <ol class="client-timeline">
            @forelse($serviceRequest->statusHistories as $history)
                @php($historyStatus = \App\Enums\ServiceRequestStatus::tryFrom($history->to_status))
                <li>
                    <strong>{{ $historyStatus?->label() ?? ucfirst(str_replace('_', ' ', $history->to_status)) }}</strong>
                    <span>{{ $history->created_at?->format('d/m/Y à H:i') ?? 'Date indisponible' }}</span>
                    <p class="request-history-actor">{{ $history->actor?->name ?? 'Système PROXIWORK' }}</p>
                    @if($history->reason && ! str_starts_with($history->reason, 'Quotation rejected by client'))
                        <p>{{ $history->reason }}</p>
                    @endif
                </li>
            @empty
                <li><strong>{{ $serviceRequest->status->label() }}</strong><span>{{ $serviceRequest->created_at?->format('d/m/Y à H:i') }}</span></li>
            @endforelse
        </ol>
    </section>
@endsection
