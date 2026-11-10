@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Examen du remboursement')
@section('page_description','La demande, la décision et le résultat externe sont conservés séparément.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.finance.refunds') }}">Retour aux remboursements</a>@endsection
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/admin/finance-operations.css'])
@endunless
@endpush
@section('content')
<div class="admin-financial-operations">
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="admin-alert admin-alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Rapprochement manuel</strong><p>Ne confirmez pas un remboursement simulé comme un transfert réel. Le succès exige une preuve externe et une référence vérifiable.</p></div></div>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">{{ $refund->reference }}</span><h3>{{ \App\Support\MoneyFormatter::format((string)$refund->amount) }} {{ $refund->currency }}</h3></div><span class="admin-refund-status admin-refund-status--{{ $refund->status->value }}">{{ ucfirst($refund->status->value) }}</span></div>
        <dl class="admin-financial-details">
            <div><dt>Client</dt><dd>{{ $refund->client?->name ?? '—' }} · {{ $refund->client?->email ?? '—' }}</dd></div>
            <div><dt>Commande</dt><dd>{{ $refund->payment?->order?->order_number ?? '—' }}</dd></div>
            <div><dt>Professionnel</dt><dd>{{ $refund->payment?->order?->professional?->user?->name ?? '—' }}</dd></div>
            <div><dt>Paiement initial</dt><dd>#{{ $refund->payment_id }} · {{ \App\Support\MoneyFormatter::format((string)$refund->payment?->amount) }} {{ $refund->payment?->currency }}</dd></div>
            <div><dt>Statut du paiement</dt><dd>{{ $refund->payment?->status?->value ?? '—' }}</dd></div>
            <div><dt>Demandé le</dt><dd>{{ $refund->requested_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            <div><dt>Motif du client</dt><dd>{{ $refund->reason }}</dd></div>
            <div><dt>Référence externe</dt><dd>{{ $refund->provider_reference ?: 'Non renseignée' }}</dd></div>
            <div><dt>Traité le</dt><dd>{{ $refund->processed_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            @if($refund->review_note)<div><dt>Note administrative</dt><dd>{{ $refund->review_note }}</dd></div>@endif
        </dl>
    </section>
    @if($refund->status === \App\Enums\PaymentRefundStatus::REQUESTED)
    <section class="admin-card"><h3>Prendre une décision</h3><div class="admin-financial-actions">
        <form method="POST" action="{{ route('admin.finance.refunds.processing',$refund) }}">@csrf<h4>Prendre en charge</h4><label class="admin-field"><span>Note d'examen obligatoire</span><textarea name="note" minlength="10" maxlength="2000" required></textarea></label><button class="button button--primary" type="submit">Démarrer l'examen</button></form>
        <form method="POST" action="{{ route('admin.finance.refunds.reject',$refund) }}">@csrf<h4>Refuser la demande</h4><label class="admin-field"><span>Motif du refus obligatoire</span><textarea name="note" minlength="10" maxlength="2000" required></textarea></label><button class="button button--danger" type="submit">Refuser</button></form>
    </div></section>
    @elseif($refund->status === \App\Enums\PaymentRefundStatus::PROCESSING)
    <section class="admin-card"><h3>Enregistrer le résultat vérifié</h3><div class="admin-financial-actions">
        <form method="POST" action="{{ route('admin.finance.refunds.succeeded',$refund) }}">@csrf<h4>Confirmer le remboursement</h4><label class="admin-field"><span>Référence externe obligatoire</span><input name="provider_reference" maxlength="191" required></label><label class="admin-field"><span>Preuve et note de rapprochement</span><textarea name="note" minlength="10" maxlength="2000" required></textarea></label><button class="button button--primary" type="submit">Confirmer le résultat</button></form>
        <form method="POST" action="{{ route('admin.finance.refunds.failed',$refund) }}">@csrf<h4>Confirmer un échec</h4><label class="admin-field"><span>Référence externe éventuelle</span><input name="provider_reference" maxlength="191"></label><label class="admin-field"><span>Motif vérifié</span><textarea name="note" minlength="10" maxlength="2000" required></textarea></label><button class="button button--danger" type="submit">Enregistrer l'échec</button></form>
    </div></section>
    @endif
    <section class="admin-card"><h3>Transaction initiale confirmée</h3>
        @forelse($refund->payment?->transactions ?? [] as $transaction)
            <div class="admin-financial-event"><strong>{{ $transaction->provider }} · {{ $transaction->provider_reference ?? $transaction->provider_transaction_id ?? 'Référence indisponible' }}</strong><span>{{ $transaction->processed_at?->format('d/m/Y H:i:s') ?? '—' }} · {{ $transaction->amount }} {{ $transaction->currency }}</span></div>
        @empty<p>Aucune transaction confirmée liée n’est disponible.</p>@endforelse
    </section>
    <section class="admin-card"><h3>Événements du remboursement</h3>
        @forelse($refund->payment?->events ?? [] as $event)
            <div class="admin-financial-event"><strong>{{ $event->event_type }}</strong><span>{{ $event->from_status ?? '—' }} → {{ $event->to_status }} · {{ $event->source }} · {{ $event->occurred_at?->format('d/m/Y H:i:s') }}</span></div>
        @empty<p>Aucun événement spécifique n’est disponible.</p>@endforelse
    </section>
</div>
@endsection
