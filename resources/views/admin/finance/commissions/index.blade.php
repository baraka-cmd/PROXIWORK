@extends('layouts.admin')
@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/finance.css')
    @endunless
@endpush
@section('page_eyebrow', 'FINANCES · REVENUS')
@section('page_title', 'Commissions')
@section('page_description', 'Contrôlez les commissions calculées et comptabilisées par le moteur financier existant.')
@section('content')
<div class="admin-finance">
    <div class="admin-finance__notice">Taux configuré pour les nouveaux calculs : <strong>{{ number_format((float) $configuredRate, 2, ',', ' ') }} %</strong>. Ce paramètre provient de la configuration serveur. Cette page ne modifie pas les commissions historiques.</div>
    <section class="admin-card">
        <div class="admin-card__header"><h3>Filtres</h3><a class="admin-link" href="{{ route('admin.finance.commissions') }}">Réinitialiser</a></div>
        <form method="GET" class="admin-filter-grid">
            <label class="admin-field admin-field--wide"><span>Recherche</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence, commande, professionnel"></label>
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(\App\Enums\CommissionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->value }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Devise</span><input name="currency" maxlength="3" value="{{ $filters['currency'] ?? '' }}" placeholder="USD"></label>
            <label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <div class="admin-filter-actions"><button class="button button--primary">Appliquer</button></div>
        </form>
    </section>
    <section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Commission</th><th>Commande</th><th>Professionnel</th><th>Base</th><th>Taux appliqué</th><th>Commission</th><th>Net professionnel</th><th>Statut</th></tr></thead><tbody>
        @forelse($commissions as $commission)
            <tr><td><a href="{{ route('admin.finance.commissions.show', $commission) }}">COM-{{ $commission->id }}</a><small>{{ $commission->posted_at?->format('d/m/Y H:i') }}</small></td><td>{{ $commission->order?->order_number ?? '#'.$commission->order_id }}</td><td>{{ $commission->professional?->user?->name ?? '—' }}</td><td>{{ number_format((float) $commission->gross_amount, 2, ',', ' ') }} {{ $commission->currency }}</td><td>{{ number_format((float) $commission->commission_rate, 2, ',', ' ') }} %</td><td>{{ number_format((float) $commission->commission_amount, 2, ',', ' ') }} {{ $commission->currency }}</td><td>{{ number_format((float) $commission->net_amount, 2, ',', ' ') }} {{ $commission->currency }}</td><td><span class="admin-status admin-status--{{ $commission->status->value }}">{{ $commission->status->value }}</span></td></tr>
        @empty
                            <tr><td colspan="8"><div class="admin-empty">Aucune commission trouvée.</div></td></tr>
                        @endforelse
    </tbody></table></div>@if ($commissions->hasPages())
            <div class="admin-pagination">{{ $commissions->links() }}</div>
        @endif</section>
</div>
@endsection
