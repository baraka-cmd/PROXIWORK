@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / SÉCURITÉ')
@section('page_title', 'Événements de sécurité')
@section('page_description', 'Connexions, événements de sécurité et décisions d’accès consignés.')

@section('content')
<section class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Catégorie</th><th>Événement</th><th>Acteur</th><th>Résultat</th><th>Gravité</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($events as $event)
                <tr>
                    <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $event->category }}</td>
                    <td><strong>{{ $event->action }}</strong></td>
                    <td>{{ $event->user?->name ?? 'Inconnu / système' }}</td>
                    <td>{{ $event->outcome }}</td>
                    <td>{{ $event->severity }}</td>
                    <td>{{ $event->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun événement de sécurité enregistré.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $events->links() }}</div>
</section>
@endsection
