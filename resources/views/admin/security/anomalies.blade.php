@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / SÉCURITÉ')
@section('page_title', 'Anomalies et alertes')
@section('page_description', 'Alertes déterministes à examiner. Une alerte n’est pas une preuve de compromission.')

@section('content')
<section class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Détectée</th><th>Règle</th><th>Gravité</th><th>État</th><th>Occurrences</th><th>Dernière activité</th><th>Traitement</th></tr></thead>
            <tbody>
            @forelse($alerts as $alert)
                <tr>
                    <td>{{ $alert->first_seen_at?->format('d/m/Y H:i') }}</td>
                    <td><strong>{{ $alert->title }}</strong><small class="admin-table__muted">{{ $alert->rule_key }}</small></td>
                    <td>{{ $alert->severity }}</td>
                    <td>{{ $alert->status }}</td>
                    <td>{{ $alert->occurrences }}</td>
                    <td>{{ $alert->last_seen_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.security.alerts.resolve', $alert) }}" class="admin-stack">
                            @csrf
                            <select name="status" required>
                                @foreach(['investigating' => 'En investigation', 'confirmed' => 'Confirmée', 'false_positive' => 'Faux positif', 'resolved' => 'Résolue', 'closed' => 'Clôturée'] as $value => $label)
                                    <option value="{{ $value }}" @selected($alert->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="note" minlength="10" maxlength="2000" required placeholder="Note d’investigation">
                            <button class="button button--secondary" type="submit">Enregistrer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune anomalie détectée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $alerts->links() }}</div>
</section>
@endsection
