@extends('layouts.professional')
@section('page_title','Litige #'.$dispute->id)
@section('page_description','Suivi du dossier associé à la commande '.($dispute->order?->order_number ?? '#'.$dispute->order_id).'.')
@section('content')
@if(session('success'))<div role="status" class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
<section class="dashboard-card"><h2>Dossier de litige</h2>
<p><strong>Statut :</strong> {{ str_replace('_',' ',$dispute->status->value) }}</p>
<p><strong>Motif :</strong> {{ $dispute->reason_code }}</p>
<p>{{ $dispute->description }}</p>
<p><strong>Commande :</strong> {{ $dispute->order?->order_number ?? '#'.$dispute->order_id }}</p>
</section>
<section class="dashboard-card"><h2>Échanges avec l’autre partie et l’administration</h2>
@forelse($dispute->messages as $message)<article><strong>{{ $message->sender?->name ?? 'Utilisateur' }}</strong><small>{{ $message->created_at?->format('d/m/Y H:i') }}</small><p>{{ $message->body }}</p></article>@empty<p>Aucun message pour le moment.</p>@endforelse
@if(!in_array($dispute->status->value,['resolved','closed'],true))
<form method="POST" action="{{ route($prefix.'disputes.messages.store',$dispute) }}">@csrf
<label for="body">Votre message</label><textarea id="body" name="body" rows="5" minlength="2" maxlength="10000" required></textarea>
<button type="submit">Envoyer</button></form>
@else<p>Ce dossier est clôturé. Il n’accepte plus de nouveaux messages.</p>@endif
</section>
@endsection
