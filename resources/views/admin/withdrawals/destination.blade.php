@extends('layouts.admin')
@section('page_eyebrow','FINANCES · ACCÈS SENSIBLE')
@section('page_title','Révélation de la destination de versement')
@section('page_description','Consultation exceptionnelle et journalisée d’une donnée financière confidentielle.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.withdrawals.show', $withdrawal) }}">Retour au dossier</a>@endsection
@section('content')
<div class="admin-withdrawals">
    <div class="admin-alert admin-alert--warning" role="alert"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><div><strong>Information confidentielle</strong><p>Cette consultation est journalisée. Ne copiez pas cette destination dans les notes, captures, exports ou communications non sécurisées.</p></div></div>
    <section class="admin-card">
        <span class="admin-card__eyebrow">WD-{{ str_pad((string)$withdrawal->getKey(), 8, '0', STR_PAD_LEFT) }}</span>
        <h3>{{ $withdrawal->professional?->user?->name ?? 'Professionnel indisponible' }}</h3>
        <dl class="admin-withdrawal-details">
            <div><dt>Montant demandé</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</dd></div>
            <div><dt>Canal</dt><dd>{{ $withdrawal->provider === 'mobile_money' ? 'Mobile Money' : 'Virement bancaire' }}</dd></div>
            <div><dt>Destination complète</dt><dd><code class="admin-withdrawal-destination">{{ $withdrawal->destination_ciphertext ?: $withdrawal->destination }}</code></dd></div>
            <div><dt>Statut</dt><dd>{{ $withdrawal->status->label() }}</dd></div>
        </dl>
    </section>
</div>
@endsection
