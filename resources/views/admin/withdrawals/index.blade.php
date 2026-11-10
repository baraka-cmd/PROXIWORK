@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Retraits professionnels')
@section('page_description','Examinez les demandes, les montants réservés et les preuves de versement.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Traitement manuel contrôlé</span>@endsection
@push('head')
@unless (app()->environment('testing'))
@vite(['resources/css/pages/admin/withdrawals.css'])
@endunless
@endpush
@section('content')
<div class="admin-withdrawals">
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Aucun fournisseur de versement réel n’est intégré.</strong><p>Les canaux sont traités manuellement. Un succès exige une preuve externe vérifiée; un résultat incertain doit rester en vérification avec les fonds bloqués.</p></div></div>
    <section class="admin-withdrawal-kpis">
        <article class="admin-card"><span>À examiner</span><strong>{{ (int)($statusCounts[\App\Enums\WithdrawalStatus::REQUESTED->value] ?? 0) }}</strong></article>
        <article class="admin-card"><span>En traitement</span><strong>{{ (int)($statusCounts[\App\Enums\WithdrawalStatus::PROCESSING->value] ?? 0) }}</strong></article>
        <article class="admin-card"><span>À vérifier</span><strong>{{ (int)($statusCounts[\App\Enums\WithdrawalStatus::REVIEW_REQUIRED->value] ?? 0) }}</strong></article>
        <article class="admin-card"><span>Terminés (succès)</span><strong>{{ (int)($statusCounts[\App\Enums\WithdrawalStatus::SUCCEEDED->value] ?? 0) }}</strong></article>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">FONDS RÉSERVÉS</span><h3>Montants actuellement bloqués par devise</h3></div></div>
        <div class="admin-withdrawal-reserved">
        @forelse($reservedTotals as $currency => $total)
            <div><strong>{{ \App\Support\MoneyFormatter::format((string)$total->amount_total) }} {{ $currency }}</strong><span>{{ (int)$total->operation_count }} demande(s) ouvertes</span></div>
        @empty
            <p>Aucun retrait ne réserve actuellement de fonds.</p>
        @endforelse
        </div>
        <p class="admin-finance__muted">Ces montants sont des réservations en cours, pas des sorties de trésorerie exécutées.</p>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">RECHERCHE ET FILTRES</span><h3>Affiner les demandes</h3></div><a class="admin-link" href="{{ route('admin.withdrawals.index') }}">Réinitialiser</a></div>
        <form method="GET" action="{{ route('admin.withdrawals.index') }}" class="admin-withdrawal-filters">
            <label class="admin-field"><span>Référence, professionnel ou preuve</span><input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Nom, e-mail, ID ou référence"></label>
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(\App\Enums\WithdrawalStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Devise</span><select name="currency"><option value="">Toutes</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Canal</span><select name="provider"><option value="">Tous</option><option value="mobile_money" @selected(($filters['provider'] ?? '') === 'mobile_money')>Mobile Money</option><option value="bank_transfer" @selected(($filters['provider'] ?? '') === 'bank_transfer')>Virement bancaire</option></select></label>
            <label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <label class="admin-field"><span>Par page</span><select name="per_page"><option value="20" @selected(($filters['per_page'] ?? 20)==20)>20</option><option value="50" @selected(($filters['per_page'] ?? 20)==50)>50</option><option value="100" @selected(($filters['per_page'] ?? 20)==100)>100</option></select></label>
            <button class="button button--primary button--small" type="submit">Appliquer les filtres</button>
        </form>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">FILE OPÉRATIONNELLE</span><h3>Demandes de retrait</h3></div><span class="admin-page-badge">{{ $withdrawals->total() }} résultat(s)</span></div>
        @if($withdrawals->isEmpty())
            <div class="admin-empty-state"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i><h3>Aucune demande</h3><p>Aucune demande ne correspond aux critères sélectionnés.</p></div>
        @else
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Référence / date</th><th>Professionnel</th><th>Montant</th><th>Canal</th><th>Attente</th><th>Statut</th><th>Action</th></tr></thead><tbody>
            @foreach($withdrawals as $withdrawal)
                <tr>
                    <td><strong>WD-{{ str_pad((string)$withdrawal->getKey(), 8, '0', STR_PAD_LEFT) }}</strong><small>{{ $withdrawal->requested_at?->format('d/m/Y H:i') ?? '—' }}</small></td>
                    <td>{{ $withdrawal->professional?->user?->name ?? 'Profil indisponible' }}<small>{{ $withdrawal->professional?->user?->email ?? '' }}</small></td>
                    <td><strong>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</strong></td>
                    <td>{{ $withdrawal->provider === 'mobile_money' ? 'Mobile Money' : 'Virement bancaire' }}</td>
                    <td>{{ $withdrawal->requested_at?->diffForHumans() ?? '—' }}</td>
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
