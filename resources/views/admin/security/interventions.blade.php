@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / AUDIT')
@section('page_title', 'Interventions administratives et financières')
@section('page_description', 'Chronologie des interventions humaines distincte des événements métier et des mouvements du registre financier.')

@section('content')
<section class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Intervenant</th><th>Domaine</th><th>Action</th><th>Ressource</th><th>Montant</th><th>Résultat</th><th>Motif</th></tr></thead>
            <tbody>
            @forelse($interventions as $item)
                <tr>
                    <td>{{ $item->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $item->actor?->name ?? 'Système' }}</td>
                    <td>{{ $item->domain }}</td>
                    <td>{{ $item->action }}</td>
                    <td>{{ $item->subject_type ? class_basename($item->subject_type).' #'.$item->subject_id : '—' }}</td>
                    <td>{{ $item->amount !== null ? $item->amount.' '.($item->currency ?? '') : '—' }}</td>
                    <td>{{ $item->result }}</td>
                    <td>{{ $item->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Aucune intervention enregistrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $interventions->links() }}</div>
</section>
@endsection
