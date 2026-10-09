@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/payments.css')
    @endunless
@endpush
@section('page_title','Paiements')@section('page_description','Supervisez les paiements et leur traçabilité sans exposer les secrets d’idempotence.')
@section('content')
<section class="admin-card"><div class="admin-card__header"><h3>Filtres</h3><a class="admin-link" href="{{ route('admin.payments.index') }}">Réinitialiser</a></div><form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><input name="search" value="{{ $filters['search']??'' }}" placeholder="ID paiement, commande, client"></label>
<label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(['initiated','pending','processing','succeeded','failed','cancelled','expired','refunded'] as $s)<option value="{{ $s }}" @selected(($filters['status']??'')===$s)>{{ $s }}</option>@endforeach</select></label>
<label class="admin-field"><span>Provider</span><select name="provider"><option value="">Tous</option>@foreach($providers as $p)<option value="{{ $p }}" @selected(($filters['provider']??'')===$p)>{{ $p }}</option>@endforeach</select></label>
<label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from']??'' }}"></label><label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to']??'' }}"></label>
<div class="admin-filter-actions"><button class="button button--primary">Appliquer</button></div></form></section>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Paiement</th><th>Commande</th><th>Client</th><th>Montant</th><th>Provider</th><th>Statut</th><th></th></tr></thead><tbody>
@forelse($payments as $payment)<tr><td><a href="{{ route('admin.payments.show',$payment) }}">#{{ $payment->id }}</a><small>{{ $payment->created_at?->format('d/m/Y H:i') }}</small></td><td><a href="{{ route('admin.orders.show',$payment->order) }}">{{ $payment->order?->order_number??'—' }}</a></td><td>{{ $payment->client?->name??'—' }}</td><td>{{ number_format((float)$payment->amount,2) }} {{ $payment->currency }}</td><td>{{ $payment->provider->value??'—' }}</td><td><span class="admin-status admin-status--{{ $payment->status->value }}">{{ $payment->status->value }}</span></td><td><a class="admin-icon-button" href="{{ route('admin.payments.show',$payment) }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td></tr>@empty<tr><td colspan="7"><div class="admin-empty"><strong>Aucun paiement.</strong></div></td></tr>@endforelse</tbody></table></div>@if($payments->hasPages())<div class="admin-pagination">{{ $payments->links() }}</div>@endif</section>
@endsection