@extends('layouts.admin')
@section('page_eyebrow','COMMUNICATION / MODÈLES')
@section('page_title','Modèles de notifications')
@section('page_description','Modèles versionnés, variables autorisées uniquement et aucun code exécutable. Les clés correspondent aux événements métier existants.')
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success">{{ session('success') }}</div>@endif
<section class="admin-card"><h3>Nouveau modèle</h3><form method="POST" action="{{ route('admin.communication.templates.store') }}" class="admin-form">@csrf
<label class="admin-field"><span>Clé d’événement</span><input name="key" required placeholder="service_request"></label><label class="admin-field"><span>Nom</span><input name="name" required></label><label class="admin-field"><span>Langue</span><select name="locale"><option value="fr">Français</option><option value="en">English</option></select></label><input type="hidden" name="channel" value="database">
<label class="admin-field"><span>Titre</span><input name="title" required maxlength="180"></label><label class="admin-field"><span>Corps</span><textarea name="body" required rows="4"></textarea></label>
<p>Variables disponibles :</p>@foreach(['prenom','nom','email','plateforme'] as $v)<label><input type="checkbox" name="variables[]" value="{{ $v }}"> {{ $v }}</label>@endforeach
<button class="button button--primary">Créer en brouillon</button></form></section>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Clé</th><th>Nom</th><th>Langue</th><th>Version</th><th>Statut</th><th>Versions</th><th>Action</th></tr></thead><tbody>
@forelse($templates as $template)
<tr><td>{{ $template->key }}</td><td>{{ $template->name }}</td><td>{{ $template->locale }}</td><td>{{ $template->version }}</td><td>{{ $template->status }}</td><td>{{ $template->versions_count }}</td><td>@if($template->status!=='active')<form method="POST" action="{{ route('admin.communication.templates.activate',$template) }}">@csrf<button class="button button--secondary button--sm">Activer</button></form>@else Actif @endif</td></tr>
<tr><td colspan="7"><details><summary>Modifier le modèle {{ $template->key }}</summary><form method="POST" action="{{ route('admin.communication.templates.update',$template) }}" class="admin-form">@csrf @method('PUT')
<label class="admin-field"><span>Nom</span><input name="name" value="{{ $template->name }}" required></label><label class="admin-field"><span>Langue</span><select name="locale"><option value="fr" @selected($template->locale==='fr')>Français</option><option value="en" @selected($template->locale==='en')>English</option></select></label>
<label class="admin-field"><span>Titre</span><input name="title" value="{{ $template->title }}" required></label><label class="admin-field"><span>Corps</span><textarea name="body" rows="3" required>{{ $template->body }}</textarea></label>
@foreach(['prenom','nom','email','plateforme'] as $variable)<label><input type="checkbox" name="variables[]" value="{{ $variable }}" @checked(in_array($variable,$template->variables??[],true))> {{ $variable }}</label>@endforeach
<button class="button button--secondary">Enregistrer une nouvelle version</button></form><hr><strong>Historique des versions</strong>@foreach($template->versions as $version)<div class="admin-version-row"><span>Version {{ $version->version }} · {{ $version->created_at?->format('d/m/Y H:i') }}</span><form method="POST" action="{{ route('admin.communication.templates.restore',[$template,$version]) }}">@csrf<button class="button button--secondary button--sm">Restaurer en brouillon</button></form></div>@endforeach</details></td></tr>
@empty<tr><td colspan="7">Aucun modèle.</td></tr>@endforelse
</tbody></table></div>{{ $templates->links() }}</section>
@endsection