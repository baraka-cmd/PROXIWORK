@extends('layouts.client')
@section('title','Mon profil')
@section('content')
@push('head')@vite('resources/css/pages/client/profile.css')@endpush
<div class="client-profile-page"><header class="client-module__header"><div><p class="client-module__eyebrow">COMPTE CLIENT</p><h1>Mon profil</h1><p>Gérez vos informations personnelles et votre compte.</p></div><a class="btn btn-primary" href="{{ route('client.profile.edit') }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Modifier</a></header>
<section class="profile-hero client-panel"><div class="profile-avatar" aria-hidden="true">{{ strtoupper(mb_substr($user->name,0,1)) }}</div><div><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><span class="status-badge">{{ ucfirst($user->status->value ?? $user->status ?? 'Actif') }}</span></div></section>
<section class="client-panel"><h2>Informations personnelles</h2><dl class="client-definition-list"><div><dt>Nom</dt><dd>{{ $user->name }}</dd></div><div><dt>Email</dt><dd>{{ $user->email }}</dd></div><div><dt>Téléphone</dt><dd>{{ $user->phone ?: 'Non renseigné' }}</dd></div><div><dt>Membre depuis</dt><dd>{{ optional($user->created_at)->format('d/m/Y') }}</dd></div></dl></section>
<section class="profile-actions"><a class="client-panel profile-action" href="{{ route('client.addresses.index') }}"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><strong>Mes adresses</strong><small>Gérer vos adresses de service</small></span></a></section></div>
@endsection