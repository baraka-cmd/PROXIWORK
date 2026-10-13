@extends('layouts.professional')

@section('title', 'Demandes de service — PROXIWORK')
@section('page_heading', 'Demandes de service')

@push('head')
    @vite('resources/css/pages/client/service-requests.css')
@endpush

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Espace professionnel</p>
            <h1>Demandes de service</h1>
            <p class="page-lead">Consultez les demandes qui vous sont adressées, répondez au client et préparez vos devis.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif

    @if($selectedService)
        <p class="page-lead">Demandes associées au service : <strong>{{ $selectedService->title }}</strong></p>
    @endif

    <section class="professional-request-filter surface-card" aria-label="Filtrer les demandes reçues">
        <form method="GET" action="{{ route('professional.requests.index') }}">
            @if(!empty($filters['service_id']))<input type="hidden" name="service_id" value="{{ $filters['service_id'] }}">@endif
            <div class="professional-request-filter__grid">
                <div>
                    <label for="professional-request-search">Recherche</label>
                    <input id="professional-request-search" name="search" type="search" maxlength="120"
                           value="{{ $filters['search'] ?? '' }}" placeholder="Titre, description ou client">
                </div>
                <div>
                    <label for="professional-request-status">Statut</label>
                    <select id="professional-request-status" name="status">
                        <option value="">Tous les statuts</option>
                        @foreach(\App\Enums\ServiceRequestStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                        <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Clôturées</option>
                    </select>
                </div>
                <div>
                    <label for="professional-request-from">Du</label>
                    <input id="professional-request-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div>
                    <label for="professional-request-to">Au</label>
                    <input id="professional-request-to" name="to" type="date" min="{{ $filters['from'] ?? '' }}" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div>
                    <label for="professional-request-sort">Ordre</label>
                    <select id="professional-request-sort" name="sort">
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récentes</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Plus anciennes</option>
                    </select>
                </div>
            </div>
            <div class="professional-request-filter__actions">
                <button class="button button--primary" type="submit">Appliquer les filtres</button>
                <a class="button button--ghost" href="{{ route('professional.requests.index') }}">Réinitialiser</a>
            </div>
        </form>
    </section>

    @php($hasRequestFilters = collect($filters)->filter(fn ($value, $key) => $value !== null && $value !== '' && ! ($key === 'sort' && $value === 'newest'))->isNotEmpty())

    @if ($requests->isEmpty())
        <x-empty-state
            :title="$hasRequestFilters ? 'Aucune demande ne correspond à ces critères' : 'Aucune demande pour le moment'"
            :description="$hasRequestFilters ? 'Modifiez les filtres ou revenez plus tard : les demandes reçues par vos services apparaîtront ici.' : 'Les demandes reçues par vos services apparaîtront ici dès qu’un client vous contactera.'"
            icon="fa-solid fa-inbox"
        />
    @else
        <div class="request-list-heading">
            <div>
                <h2>Demandes reçues</h2>
                <p>{{ $requests->total() }} résultat{{ $requests->total() > 1 ? 's' : '' }} dans votre espace professionnel.</p>
            </div>
        </div>
        <div class="table-responsive surface-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Demande</th>
                        <th>Service</th>
                        <th>Client</th>
                        <th>Statut</th>
                        <th>Reçue le</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $serviceRequest)
                        <tr>
                            <td>
                                <strong>{{ $serviceRequest->title }}</strong>
                                <p class="table-secondary">PW-REQ-{{ str_pad((string) $serviceRequest->id, 6, '0', STR_PAD_LEFT) }}</p>
                            </td>
                            <td>{{ $serviceRequest->service?->title ?? 'Service indisponible' }}</td>
                            <td>{{ $serviceRequest->client?->name ?? 'Client' }}</td>
                            <td><span class="request-status-badge request-status-badge--{{ $serviceRequest->status->value }}">{{ $serviceRequest->status->label() }}</span></td>
                            <td>{{ $serviceRequest->requested_at?->format('d/m/Y H:i') ?? $serviceRequest->created_at?->format('d/m/Y H:i') }}</td>
                            <td><a class="button button--ghost button--sm" href="{{ route('professional.requests.show', $serviceRequest) }}">Consulter</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="professional-pagination">{{ $requests->links() }}</div>
    @endif
@endsection
