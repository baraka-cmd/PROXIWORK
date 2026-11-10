@extends('layouts.admin')
@section('title', 'Modèles de notifications')
@section('content')
<div class="admin-page">
    <h1>Modèles de notifications</h1>
    <p>Modèles texte versionnés. Les variables sont limitées à une liste autorisée ; aucun code HTML ou PHP n’est exécuté.</p>
    <section class="admin-card">
        <h2>Nouveau modèle</h2>
        <form method="POST" action="{{ route('admin.communication.templates.store') }}">@csrf
            <label>Nom <input name="name" maxlength="120" required></label>
            <label>Code unique <input name="code" maxlength="120" required></label>
            <label>Événement <select name="event_key">@foreach($events as $event)<option value="{{ $event }}">{{ $event }}</option>@endforeach</select></label>
            <label>Catégorie <select name="category">@foreach(['account','requests','quotes','orders','payments','messages','reviews'] as $v)<option>{{ $v }}</option>@endforeach</select></label>
            <label>Canal <select name="channel"><option value="database">Notification interne</option><option value="mail">E-mail</option></select></label>
            <label>Langue <select name="locale"><option value="fr">Français</option><option value="en">English</option></select></label>
            <label>Objet e-mail <input name="subject" maxlength="180"></label>
            <label>Titre <input name="title" maxlength="180" required></label>
            <label>Message <textarea name="body" rows="5" maxlength="10000" required></textarea></label>
            <button type="submit">Enregistrer le brouillon</button>
        </form>
    </section>
    <section class="admin-card"><h2>Modèles existants</h2>
        <div class="admin-table-container"><table class="admin-table"><thead><tr><th>Nom</th><th>Code</th><th>Événement</th><th>Canal</th><th>Version</th><th>État</th><th>Action</th></tr></thead><tbody>
        @forelse($templates as $template)<tr><td>{{ $template->name }}</td><td>{{ $template->code }}</td><td>{{ $template->event_key }}</td><td>{{ $template->channel }}</td><td>v{{ $template->version }} · {{ $template->versions_count }} snapshot(s)</td><td>{{ $template->status }}</td><td>
@if($template->status === 'draft')
<details><summary>Modifier le brouillon</summary><form method="POST" action="{{ route('admin.communication.templates.update',$template) }}">@csrf @method('PUT')
<label>Nom <input name="name" value="{{ $template->name }}" maxlength="120" required></label>
<label>Objet e-mail <input name="subject" value="{{ $template->subject }}" maxlength="180"></label>
<label>Titre <input name="title" value="{{ $template->title }}" maxlength="180" required></label>
<label>Message <textarea name="body" rows="4" maxlength="10000" required>{{ $template->body }}</textarea></label>
<button>Enregistrer une version</button></form></details>
@if(auth()->user()?->hasPermissionTo('notification_templates.publish'))<form method="POST" action="{{ route('admin.communication.templates.publish',$template) }}">@csrf<button type="submit">Publier</button></form>@endif
@elseif($template->status === 'published')
<form method="POST" action="{{ route('admin.communication.templates.revise',$template) }}">@csrf<button type="submit">Créer une nouvelle révision</button></form>
@endif
</td></tr>@empty<tr><td colspan="7">Aucun modèle.</td></tr>@endforelse
        </tbody></table></div>{{ $templates->links() }}
    </section>
</div>
@endsection
