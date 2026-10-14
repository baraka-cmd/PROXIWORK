@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Retraits professionnels')
@section('page_description','Examinez les demandes de retrait et vérifiez les preuves avant toute confirmation financière.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Accès financier contrôlé</span>@endsection
@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/withdrawals.css'])
    @endunless
@endpush
@section('content')
<div class="admin-withdrawals">
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ session('success') }}</div>@endif
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Aucun fournisseur de versement réel n’est intégré.</strong><p>Les opérations ci-dessous sont manuelles. Ne confirmez un succès qu’après vérification d’une preuve externe fiable et de la référence correspondante.</p></div></div>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Recherche</span><h3>Filtrer les demandes</h3></div><a class="admin-link" href="{{ route('admin.withdrawals.index') }}">Réinitialiser</a></div>
        <form method="GET" action="{{ route('admin.withdrawals.index') }}" class="admin-withdrawal-filters">
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(\App\Enums\WithdrawalStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Devise</span><select name="currency"><option value="">Toutes</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>@endforeach</select></label>
            <button class="button button--primary button--small" type="submit">Filtrer</button>
        </form>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Registre opérationnel</span><h3>Demandes de retrait</h3></div><span class="admin-page-badge">{{ $withdrawals->total() }} demande(s)</span></div>
        @if($withdrawals->isEmpty())
            <div class="admin-empty-state"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i><h3>Aucune demande</h3><p>Les demandes de retrait apparaîtront ici.</p></div>
        @else
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Référence</th><th>Professionnel</th><th>Montant</th><th>Soumise le</th><th>Statut</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
            @foreach($withdrawals as $withdrawal)
                <tr>
                    <td><strong>WD-{{ str_pad((string)$withdrawal->getKey(), 8, '0', STR_PAD_LEFT) }}</strong></td>
                    <td>{{ $withdrawal->professional?->user?->name ?? 'Profil indisponible' }}<small>{{ $withdrawal->professional?->user?->email ?? '' }}</small></td>
                    <td><strong>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</strong></td>
                    <td>{{ $withdrawal->requested_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td><span class="admin-withdrawal-status admin-withdrawal-status--{{ $withdrawal->status->value }}">{{ $withdrawal->status->label() }}</span></td>
                    <td><a class="button button--secondary button--small" href="{{ route('admin.withdrawals.show',$withdrawal) }}">Examiner</a></td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="admin-pagination">{{ $withdrawals->links() }}</div>
        @endif
    </section>
</div>
@endsection
