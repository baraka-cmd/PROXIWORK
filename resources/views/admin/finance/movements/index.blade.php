@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Mouvements financiers')
@section('page_description','Registre immuable des écritures de portefeuilles professionnels.')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/admin/finance-operations.css'])
@endunless
@endpush
@section('content')
@php($movementTypeLabels = ['earning' => 'Revenu professionnel', 'commission' => 'Commission', 'withdrawal' => 'Retrait', 'refund' => 'Remboursement', 'adjustment' => 'Ajustement', 'reversal' => 'Contre-écriture'])
@php($bucketLabels = ['available' => 'Disponible', 'pending' => 'En attente', 'locked' => 'Bloqué'])
<div class="admin-financial-operations">
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-lock" aria-hidden="true"></i><div><strong>Registre en lecture seule</strong><p>Les écritures ne peuvent être ni modifiées ni supprimées. Une correction doit créer une nouvelle écriture traçable via le processus métier autorisé.</p></div></div>
    <section class="admin-card">
        <form method="GET" action="{{ route('admin.finance.movements') }}" class="admin-financial-filters">
            <label class="admin-field"><span>Recherche / professionnel / référence</span><input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120"></label>
            <label class="admin-field"><span>Devise</span><select name="currency"><option value="">Toutes</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '')===$currency)>{{ $currency }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Type</span><select name="type"><option value="">Tous</option>@foreach(['earning'=>'Revenu','commission'=>'Commission','withdrawal'=>'Retrait','refund'=>'Remboursement','adjustment'=>'Ajustement','reversal'=>'Contre-écriture'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['type'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Sens</span><select name="direction"><option value="">Tous</option><option value="credit" @selected(($filters['direction'] ?? '')==='credit')>Crédit</option><option value="debit" @selected(($filters['direction'] ?? '')==='debit')>Débit</option></select></label>
            <label class="admin-field"><span>Compartiment</span><select name="bucket"><option value="">Tous</option>@foreach(['available'=>'Disponible','pending'=>'En attente','locked'=>'Bloqué'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['bucket'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <button class="button button--primary button--small" type="submit">Filtrer</button>
            <a class="button button--secondary button--small" href="{{ route('admin.finance.movements') }}">Réinitialiser</a>
        </form>
    </section>
    <section class="admin-card"><div class="admin-card__header"><h3>Registre des mouvements</h3><span class="admin-page-badge">{{ $movements->total() }} écriture(s)</span></div>
    @if($movements->isEmpty())<div class="admin-empty-state"><h3>Aucun mouvement</h3><p>Aucune écriture ne correspond aux filtres.</p></div>
    @else
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Professionnel</th><th>Type / compartiment</th><th>Sens</th><th>Montant</th><th>Solde après</th><th>Référence source</th><th></th></tr></thead><tbody>
    @foreach($movements as $movement)
        <tr><td>{{ $movement->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $movement->wallet?->professional?->user?->name ?? '—' }}</td><td>{{ $movementTypeLabels[$movement->type->value] ?? $movement->type->value }}<small>{{ $bucketLabels[$movement->balance_bucket] ?? '—' }}</small></td><td>{{ $movement->direction->value === 'credit' ? 'Crédit' : 'Débit' }}</td><td><strong>{{ \App\Support\MoneyFormatter::format((string)$movement->amount) }} {{ $movement->currency }}</strong></td><td>{{ \App\Support\MoneyFormatter::format((string)$movement->balance_after) }} {{ $movement->currency }}</td><td>{{ class_basename($movement->reference_type ?? '') }} #{{ $movement->reference_id ?? '—' }}</td><td><a class="button button--secondary button--small" href="{{ route('admin.finance.movements.show',$movement) }}">Détail</a></td></tr>
    @endforeach
    </tbody></table></div><div class="admin-pagination">{{ $movements->links() }}</div>
    @endif
    </section>
</div>
@endsection
