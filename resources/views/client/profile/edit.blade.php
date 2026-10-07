@extends('layouts.client')
@section('title','Modifier mon profil')
@section('content')
@push('head')@vite('resources/css/pages/client/profile.css')@endpush
<div class="client-profile-page"><header class="client-module__header"><div><p class="client-module__eyebrow">COMPTE CLIENT</p><h1>Modifier mon profil</h1></div></header>
@if($errors->any())<div class="client-panel client-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="client-panel"><form method="POST" action="{{ route('client.profile.update') }}" class="profile-form">@csrf @method('PATCH')
<label>Nom<input type="text" name="name" value="{{ old('name',$user->name) }}" required maxlength="120"></label>
<label>Email<input type="email" value="{{ $user->email }}" disabled><small>L’adresse email est gérée par le compte.</small></label>
<label>Téléphone<input type="tel" name="phone" value="{{ old('phone',$user->phone) }}" maxlength="30" autocomplete="tel"></label>
<button class="btn btn-primary" type="submit">Enregistrer</button></form></section></div>
@endsection