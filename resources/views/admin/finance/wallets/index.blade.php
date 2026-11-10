@extends('layouts.admin')
@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/finance.css')
    @endunless
@endpush
@section('page_eyebrow', 'FINANCES · PROFESSIONNELS')
@section('page_title', 'Portefeuilles professionnels')
@section('page_description', 'Supervisez les soldes disponibles, en attente et bloqués. Aucun solde ne peut être modifié depuis cet écran.')
@section('content')
<div class="admin-finance">
    <div class="admin-finance__notice">Espace de consultation et de contrôle uniquement. Les soldes sont mis à jour par les services financiers et les écritures du registre restent immuables.</div>
    <section class="admin-card">
        <div class="admin-card__header"><h3>Recherche et filtres</h3><a class="admin-link" href="{{ route('admin.finance.wallets') }}">Réinitialiser</a></div>
        <form method="GET" class="admin-filter-grid">
            <label class="admin-field admin-field--wide"><span>Professionnel ou référence</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, e-mail ou ID portefeuille"></label>
            <label class="admin-field"><span>Devise</span><input name="currency" maxlength="3" value="{{ $filters['currency'] ?? '' }}" placeholder="USD"></label>
            <label class="admin-field"><span>État du portefeuille</span><input name="wallet_status" value="{{ $filters['wallet_status'] ?? '' }}" placeholder="active"></label>
            <div class="admin-filter-actions"><button class="button button--primary">Appliquer</button></div>
        </form>
    </section>
    <section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Professionnel</th><th>Portefeuille</th><th>Disponible</th><th>En attente</th><th>Bloqué</th><th>Devise</th><th>Statut</th><th>Actualisé</th></tr></thead><tbody>
        @forelse($wallets as $wallet)
            <tr><td><strong>{{ $wallet->professional?->user?->name ?? 'Profil professionnel indisponible' }}</strong><small>{{ $wallet->professional?->user?->email ?? '—' }}</small></td><td><a href="{{ route('admin.finance.wallets.show', $wallet) }}">WAL-{{ $wallet->id }}</a></td><td>{{ number_format((float) $wallet->available_balance, 2, ',', ' ') }} {{ $wallet->currency }}</td><td>{{ number_format((float) $wallet->pending_balance, 2, ',', ' ') }} {{ $wallet->currency }}</td><td>{{ number_format((float) $wallet->locked_balance, 2, ',', ' ') }} {{ $wallet->currency }}</td><td>{{ $wallet->currency }}</td><td><span class="admin-status">{{ $wallet->status }}</span></td><td>{{ $wallet->updated_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        @empty
                            <tr><td colspan="8"><div class="admin-empty">Aucun portefeuille trouvé.</div></td></tr>
                        @endforelse
    </tbody></table></div>@if ($wallets->hasPages())
            <div class="admin-pagination">{{ $wallets->links() }}</div>
        @endif</section>
</div>
@endsection
