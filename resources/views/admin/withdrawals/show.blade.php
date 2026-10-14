@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Examen du retrait')
@section('page_description','Les décisions financières sont journalisées. Une confirmation de succès exige une preuve de paiement vérifiée.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.withdrawals.index') }}">Retour à la liste</a>@endsection
@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/withdrawals.css'])
    @endunless
@endpush
@section('content')
<div class="admin-withdrawals">
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="admin-alert admin-alert--error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Traitement manuel uniquement</strong><p>PROXIWORK ne transmet pas automatiquement ce versement à un prestataire. Vérifiez la destination, le montant, la devise et la preuve externe avant de confirmer un résultat.</p></div></div>

    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">WD-{{ str_pad((string)$withdrawal->getKey(), 8, '0', STR_PAD_LEFT) }}</span><h3>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</h3></div><span class="admin-withdrawal-status admin-withdrawal-status--{{ $withdrawal->status->value }}">{{ $withdrawal->status->label() }}</span></div>
        <dl class="admin-withdrawal-details">
            <div><dt>Professionnel</dt><dd>{{ $withdrawal->professional?->user?->name ?? 'Profil indisponible' }} · {{ $withdrawal->professional?->user?->email ?? '—' }}</dd></div>
            <div><dt>Montant brut demandé</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</dd></div>
            <div><dt>Frais</dt><dd>{{ $withdrawal->fee_amount !== null ? \App\Support\MoneyFormatter::format((string)$withdrawal->fee_amount).' '.$withdrawal->currency : 'Non configurés' }}</dd></div>
            <div><dt>Montant net</dt><dd>{{ $withdrawal->net_amount !== null ? \App\Support\MoneyFormatter::format((string)$withdrawal->net_amount).' '.$withdrawal->currency : 'Non estimé — barème absent' }}</dd></div>
            <div><dt>Moyen demandé</dt><dd>{{ $withdrawal->provider === 'mobile_money' ? 'Mobile Money — manuel' : ($withdrawal->provider === 'bank_transfer' ? 'Virement bancaire — manuel' : $withdrawal->provider) }}</dd></div>
            <div><dt>Destination complète — accès financier journalisé</dt><dd><code class="admin-withdrawal-destination">{{ $withdrawal->destination_ciphertext ?: $withdrawal->destination }}</code></dd></div>
            <div><dt>Demandé le</dt><dd>{{ $withdrawal->requested_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            @if($withdrawal->processing_at)<div><dt>Traitement commencé</dt><dd>{{ $withdrawal->processing_at->format('d/m/Y H:i:s') }}</dd></div>@endif
            @if($withdrawal->completed_at)<div><dt>Terminé le</dt><dd>{{ $withdrawal->completed_at->format('d/m/Y H:i:s') }}</dd></div>@endif
            @if($withdrawal->provider_transaction_id)<div><dt>Référence de paiement enregistrée</dt><dd>{{ $withdrawal->provider_transaction_id }}</dd></div>@endif
            @if($withdrawal->failure_code)<div><dt>Code de résultat</dt><dd>{{ $withdrawal->failure_code }}</dd></div>@endif
            @if($withdrawal->failure_message)<div><dt>Explication</dt><dd>{{ $withdrawal->failure_message }}</dd></div>@endif
        </dl>
    </section>

    @if($withdrawal->status === \App\Enums\WithdrawalStatus::REQUESTED)
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Décision</span><h3>Examiner la demande</h3></div></div>
            <div class="admin-withdrawal-actions-grid">
                <form method="POST" action="{{ route('admin.withdrawals.processing',$withdrawal) }}" class="admin-withdrawal-action">
                    @csrf
                    <h4>Démarrer le traitement manuel</h4><p>Cette action ne prouve pas qu’un transfert a été envoyé.</p>
                    <label class="admin-field"><span>Note de décision (obligatoire)</span><textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                    <button class="button button--primary" type="submit">Démarrer le traitement</button>
                </form>
                <form method="POST" action="{{ route('admin.withdrawals.reject',$withdrawal) }}" class="admin-withdrawal-action">
                    @csrf
                    <h4>Refuser et libérer la réservation</h4><p>Réservé aux demandes qui n’ont pas commencé le traitement.</p>
                    <label class="admin-field"><span>Motif du refus (obligatoire)</span><textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                    <button class="button button--danger" type="submit">Refuser la demande</button>
                </form>
            </div>
        </section>
    @elseif(in_array($withdrawal->status, [\App\Enums\WithdrawalStatus::PROCESSING, \App\Enums\WithdrawalStatus::REVIEW_REQUIRED], true))
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Rapprochement</span><h3>Enregistrer le résultat vérifié</h3></div></div>
            <div class="admin-withdrawal-actions-grid">
                <form method="POST" action="{{ route('admin.withdrawals.succeeded',$withdrawal) }}" class="admin-withdrawal-action">
                    @csrf
                    <h4>Confirmer le succès</h4><p>Ne confirmez qu’après avoir vérifié la preuve externe. Une référence est obligatoire.</p>
                    <label class="admin-field"><span>Référence de transaction / preuve</span><input name="provider_transaction_id" maxlength="191" value="{{ old('provider_transaction_id') }}" required></label>
                    <label class="admin-field"><span>Preuve et note de rapprochement</span><textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                    <button class="button button--primary" type="submit">Confirmer le paiement</button>
                </form>
                <form method="POST" action="{{ route('admin.withdrawals.failed',$withdrawal) }}" class="admin-withdrawal-action">
                    @csrf
                    <h4>Confirmer un échec</h4><p>Ne libérez les fonds que si l’échec est confirmé et non ambigu.</p>
                    <label class="admin-field"><span>Code de résultat</span><input name="failure_code" maxlength="64" pattern="[A-Z0-9_-]+" value="{{ old('failure_code') }}" required></label>
                    <label class="admin-field"><span>Motif vérifié</span><textarea name="failure_message" rows="3" minlength="10" maxlength="2000" required>{{ old('failure_message') }}</textarea></label>
                    <button class="button button--danger" type="submit">Confirmer l’échec et libérer</button>
                </form>
                @if($withdrawal->status === \App\Enums\WithdrawalStatus::PROCESSING)
                    <form method="POST" action="{{ route('admin.withdrawals.review-required',$withdrawal) }}" class="admin-withdrawal-action">
                        @csrf
                        <h4>Placer en vérification</h4><p>Utilisez cette option si le prestataire ou la preuve ne permet pas de déterminer le résultat. Les fonds restent bloqués.</p>
                        <label class="admin-field"><span>Motif de vérification</span><textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                        <button class="button button--secondary" type="submit">Mettre à vérifier</button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Traçabilité</span><h3>Historique des statuts</h3></div></div>
        @if($withdrawal->statusHistories->isEmpty())
            <p>Aucun événement d’historique n’est disponible pour cette ancienne demande.</p>
        @else
            <ol class="admin-withdrawal-timeline">
            @foreach($withdrawal->statusHistories as $history)
                <li><div><strong>{{ $history->to_status?->label() ?? (string)$history->to_status }}</strong><small>{{ $history->created_at?->format('d/m/Y H:i:s') }} · {{ $history->actor?->name ?? ($history->actor_type === 'system' ? 'Système' : ucfirst($history->actor_type)) }}</small>@if($history->reason)<p>{{ $history->reason }}</p>@endif</div></li>
            @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
