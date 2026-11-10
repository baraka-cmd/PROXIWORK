@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Rapports financiers')
@section('page_description','Rapports opérationnels fondés sur les opérations réellement confirmées et comptabilisées.')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/admin/finance-operations.css'])
@endunless
@endpush
@section('content')
<div class="admin-financial-operations">
    <section class="admin-card">
        <div class="admin-card__header"><h3>Paramètres du rapport</h3>
        @can('financial_reports.export')<a class="button button--secondary button--small" href="{{ route('admin.finance.reports.export',request()->query()) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Exporter CSV</a>@endcan</div>
        <form method="GET" action="{{ route('admin.finance.reports') }}" class="admin-financial-filters">
            <label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? $report['from']->toDateString() }}"></label>
            <label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? $report['to']->toDateString() }}"></label>
            <label class="admin-field"><span>Devise</span><select name="currency"><option value="">Toutes (séparées)</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '')===$currency)>{{ $currency }}</option>@endforeach</select></label>
            <label class="admin-field"><span>ID professionnel (facultatif)</span><input type="number" min="1" name="professional_id" value="{{ $filters['professional_id'] ?? '' }}"></label>
            <button class="button button--primary button--small" type="submit">Générer</button>
        </form>
    </section>
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div><strong>Rapports opérationnels</strong><p>Les paiements utilisent paid_at, les remboursements processed_at, les commissions posted_at et les retraits completed_at. Les soldes de portefeuilles sont une photographie actuelle, non un flux de la période. Les devises ne sont jamais additionnées entre elles.</p></div></div>
    <section class="admin-card"><h3>Synthèse par devise</h3>
    @forelse($report['by_currency'] as $currency=>$row)
        <div class="admin-report-currency"><h4>{{ $currency }}</h4><div class="admin-report-grid">
            <div><span>Paiements confirmés ({{ $row['payment_count'] }})</span><strong>{{ \App\Support\MoneyFormatter::format($row['payments']) }} {{ $currency }}</strong></div>
            <div><span>Remboursements réussis ({{ $row['refund_count'] }})</span><strong>{{ \App\Support\MoneyFormatter::format($row['refunds']) }} {{ $currency }}</strong></div>
            <div><span>Net après remboursements</span><strong>{{ \App\Support\MoneyFormatter::format($row['net_collected_after_refunds']) }} {{ $currency }}</strong></div>
            <div><span>Commissions comptabilisées ({{ $row['commission_count'] }})</span><strong>{{ \App\Support\MoneyFormatter::format($row['commission']) }} {{ $currency }}</strong></div>
            <div><span>Net attribué aux professionnels</span><strong>{{ \App\Support\MoneyFormatter::format($row['professional_net']) }} {{ $currency }}</strong></div>
            <div><span>Retraits réussis ({{ $row['withdrawal_count'] }})</span><strong>{{ \App\Support\MoneyFormatter::format($row['withdrawals']) }} {{ $currency }}</strong></div>
            <div><span>Solde disponible actuel</span><strong>{{ \App\Support\MoneyFormatter::format($row['available_balance']) }} {{ $currency }}</strong></div>
            <div><span>Solde en attente / bloqué actuel</span><strong>{{ \App\Support\MoneyFormatter::format($row['pending_balance']) }} / {{ \App\Support\MoneyFormatter::format($row['locked_balance']) }} {{ $currency }}</strong></div>
        </div></div>
    @empty
        <div class="admin-empty-state"><h3>Aucune donnée financière pour cette période</h3><p>Essayez une autre période ou retirez le filtre professionnel.</p></div>
    @endforelse
    </section>
    <section class="admin-card"><h3>Définitions des indicateurs</h3><ul class="admin-report-definitions">@foreach($report['definitions'] as $label=>$definition)<li><strong>{{ ucfirst(str_replace('_',' ',$label)) }} :</strong> {{ $definition }}</li>@endforeach</ul></section>
</div>
@endsection
