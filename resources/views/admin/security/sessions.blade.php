@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / SÉCURITÉ')
@section('page_title', 'Sessions et appareils')
@section('page_description', 'Sessions web et jetons Sanctum suivis séparément. Les identifiants techniques ne sont jamais affichés.')

@section('content')
<section class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Compte</th><th>Type</th><th>Appareil</th><th>IP</th><th>Dernière activité</th><th>Expiration</th><th>État</th><th></th></tr></thead>
            <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td>{{ $session->user?->name ?? 'Compte supprimé' }}<small class="admin-table__muted">{{ $session->user?->email }}</small></td>
                    <td>{{ $session->guard === 'sanctum' ? 'API / Mobile' : 'Web' }}</td>
                    <td>{{ $session->device_name ?? 'Appareil inconnu' }}<small class="admin-table__muted">{{ $session->user_agent }}</small></td>
                    <td>{{ $session->ip_address ?? '—' }}</td>
                    <td>{{ $session->last_seen_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $session->expires_at?->format('d/m/Y H:i') ?? 'Selon le fournisseur' }}</td>
                    <td>{{ $session->isCurrent() ? 'Active' : ($session->revoked_at ? 'Révoquée' : 'Expirée') }}</td>
                    <td>
                        @if($session->isCurrent())
                            <form method="POST" action="{{ route('admin.security.sessions.revoke', $session) }}">
                                @csrf
                                <input type="hidden" name="reason" value="Révocation administrative après examen de sécurité.">
                                <button class="button button--danger" type="submit">Révoquer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Aucune session suivie.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $sessions->links() }}</div>
</section>
@endsection
