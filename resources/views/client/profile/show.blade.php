@extends('layouts.client')
@section('title','Mon profil')
@section('content')
@push('head')@vite('resources/css/pages/client/profile.css')@endpush
<div class="client-profile-page">
<header class="client-module__header"><div><p class="client-module__eyebrow">COMPTE CLIENT</p><h1>Mon profil</h1><p>Gérez vos informations personnelles et votre compte.</p></div><a class="btn btn-primary" href="{{ route('client.profile.edit') }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Modifier</a></header>
@if(session('success'))<div class="client-panel client-alert" role="status">{{ session('success') }}</div>@endif
<section class="profile-hero client-panel"><div class="profile-avatar" aria-hidden="true">{{ strtoupper(mb_substr($user->profile?->first_name ?: $user->name,0,1)) }}</div><div><h2>{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name }}</h2><p>{{ $user->email }}</p><span class="status-badge">{{ $user->account_status->value ?? 'Actif' }}</span></div></section>
<section class="client-panel"><h2>Informations personnelles</h2><dl class="client-definition-list"><div><dt>Prénom</dt><dd>{{ $user->profile?->first_name ?: 'Non renseigné' }}</dd></div><div><dt>Nom</dt><dd>{{ $user->profile?->last_name ?: 'Non renseigné' }}</dd></div><div><dt>Email</dt><dd>{{ $user->email }}</dd></div><div><dt>Téléphone</dt><dd>{{ $user->profile?->phone ?: 'Non renseigné' }}</dd></div><div><dt>Bio</dt><dd>{{ $user->profile?->bio ?: 'Non renseignée' }}</dd></div><div><dt>Membre depuis</dt><dd>{{ optional($user->created_at)->format('d/m/Y') }}</dd></div></dl></section>
<section class="profile-actions"><a class="client-panel profile-action" href="{{ route('client.addresses.index') }}"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><strong>Mes adresses</strong><small>Gérer vos adresses de service</small></span></a></section>
</div>
@endsection