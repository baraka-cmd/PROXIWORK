@extends('layouts.professional')
@section('title','Litige #'.$dispute->id)
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

<section class="dashboard-card"><h2>Pièces justificatives</h2>
<ul>@forelse($dispute->attachments as $attachment)<li><a href="{{ route($prefix.'disputes.attachments.download', [$dispute, $attachment]) }}">{{ $attachment->original_name }}</a> — {{ number_format($attachment->size / 1024, 1) }} Ko · ajouté par {{ $attachment->uploader?->name }}</li>@empty<li>Aucune pièce justificative.</li>@endforelse</ul>
@if(!in_array($dispute->status->value,['resolved','closed'],true))
<form method="POST" enctype="multipart/form-data" action="{{ route($prefix.'disputes.attachments.store',$dispute) }}">@csrf
<label for="evidence">Ajouter une preuve (PDF, JPG, PNG ou WebP, 10 Mo maximum)</label><input id="evidence" type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
<button type="submit">Téléverser la preuve</button></form>@endif
</section>

@endsection
