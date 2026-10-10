@extends('layouts.professional')

@section('title', 'Demandes de service — PROXIWORK')
@section('page_heading', 'Demandes de service')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Espace professionnel</p>
            <h1>Demandes de service</h1>
            <p class="page-lead">Consultez les demandes qui vous sont adressées et préparez vos devis.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif

    @if ($requests->isEmpty())
        <x-empty-state
            title="Aucune demande pour le moment"
            description="Les nouvelles demandes liées à vos services publiés apparaîtront ici."
            icon="fa-solid fa-inbox"
        />
    @else
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
                                <p class="table-secondary">#{{ $serviceRequest->getKey() }}</p>
                            </td>
                            <td>{{ $serviceRequest->service?->title ?? 'Service indisponible' }}</td>
                            <td>{{ $serviceRequest->client?->name ?? 'Client' }}</td>
                            <td><span class="badge badge--neutral">{{ ucfirst($serviceRequest->status->value) }}</span></td>
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
