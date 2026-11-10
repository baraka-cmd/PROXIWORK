@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Demandes de remboursement')
@section('page_description','Examinez les demandes client, suivez les décisions et rapprochez les résultats vérifiés.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Décision financière auditée</span>@endsection
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/admin/finance-operations.css'])
@endunless
@endpush
@section('content')
@php($refundStatusLabels = ['requested' => 'À examiner', 'processing' => 'En traitement', 'succeeded' => 'Réussi', 'failed' => 'Échec', 'rejected' => 'Refusé', 'cancelled' => 'Annulé'])
<div class="admin-financial-operations">
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Demande ≠ remboursement exécuté</strong><p>PROXIWORK n’intègre pas actuellement de fournisseur réel de remboursement. Ne confirmez un succès qu’après un transfert externe vérifié et une référence probante. Une simulation n’est jamais un transfert réel.</p></div></div>
    <section class="admin-financial-kpis">
        <article class="admin-card"><span>À examiner</span><strong>{{ (int)($statusCounts['requested'] ?? 0) }}</strong></article>
        <article class="admin-card"><span>En traitement</span><strong>{{ (int)($statusCounts['processing'] ?? 0) }}</strong></article>
        <article class="admin-card"><span>Réussis</span><strong>{{ (int)($statusCounts['succeeded'] ?? 0) }}</strong></article>
        <article class="admin-card"><span>Échecs / refus</span><strong>{{ (int)($statusCounts['failed'] ?? 0) + (int)($statusCounts['rejected'] ?? 0) }}</strong></article>
    </section>
    <section class="admin-card">
        <h3>Demandes ouvertes par devise</h3>
        <div class="admin-financial-currency-grid">
            @forelse($openTotals as $currency => $total)
                <div><strong>{{ \App\Support\MoneyFormatter::format((string)$total->amount_total) }} {{ $currency }}</strong><span>{{ (int)$total->operation_count }} demande(s) ouvertes</span></div>
            @empty
                <p>Aucune demande ouverte.</p>
            @endforelse
        </div>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">RECHERCHE</span><h3>Filtrer les demandes</h3></div><a class="admin-link" href="{{ route('admin.finance.refunds') }}">Réinitialiser</a></div>
        <form method="GET" action="{{ route('admin.finance.refunds') }}" class="admin-financial-filters">
            <label class="admin-field"><span>Référence, client, professionnel, commande</span><input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120"></label>
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(\App\Enums\PaymentRefundStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '')===$status->value) >{{ $refundStatusLabels[$status->value] ?? $status->value }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Devise</span><select name="currency"><option value="">Toutes</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '')===$currency)>{{ $currency }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <button class="button button--primary button--small" type="submit">Filtrer</button>
        </form>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">REGISTRE</span><h3>Demandes de remboursement</h3></div><span class="admin-page-badge">{{ $refunds->total() }} résultat(s)</span></div>
        @if($refunds->isEmpty())
            <div class="admin-empty-state"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><h3>Aucune demande</h3><p>Aucune demande ne correspond aux filtres sélectionnés.</p></div>
        @else
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Référence / date</th><th>Client</th><th>Commande / professionnel</th><th>Montant demandé</th><th>Statut</th><th>Action</th></tr></thead><tbody>
            @foreach($refunds as $refund)
                <tr>
                    <td><strong>{{ $refund->reference }}</strong><small>{{ $refund->requested_at?->format('d/m/Y H:i') ?? '—' }}</small></td>
                    <td>{{ $refund->client?->name ?? 'Client indisponible' }}<small>{{ $refund->client?->email ?? '' }}</small></td>
                    <td>{{ $refund->payment?->order?->order_number ?? 'Commande indisponible' }}<small>{{ $refund->payment?->order?->professional?->user?->name ?? '—' }}</small></td>
                    <td><strong>{{ \App\Support\MoneyFormatter::format((string)$refund->amount) }} {{ $refund->currency }}</strong></td>
                    <td><span class="admin-refund-status admin-refund-status--{{ $refund->status->value }}" >{{ $refundStatusLabels[$refund->status->value] ?? $refund->status->value }}</span></td>
                    <td><a class="button button--secondary button--small" href="{{ route('admin.finance.refunds.show',$refund) }}">Examiner</a></td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="admin-pagination">{{ $refunds->links() }}</div>
        @endif
    </section>
</div>
@endsection
