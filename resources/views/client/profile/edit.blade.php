@extends('layouts.client')
@section('title','Modifier mon profil')
@section('content')
@push('head')@vite('resources/css/pages/client/profile.css')@endpush
<div class="client-profile-page"><header class="client-module__header"><div><p class="client-module__eyebrow">COMPTE CLIENT</p><h1>Modifier mon profil</h1></div></header>
@if($errors->any())<div class="client-panel client-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="client-panel"><form method="POST" action="{{ route('client.profile.update') }}" class="profile-form">@csrf @method('PATCH')
<label>Prénom<input type="text" name="first_name" value="{{ old('first_name',$user->profile?->first_name) }}" required maxlength="100"></label>
<label>Nom<input type="text" name="last_name" value="{{ old('last_name',$user->profile?->last_name) }}" required maxlength="100"></label>
<label>Email<input type="email" value="{{ $user->email }}" disabled><small>L’adresse email est gérée par le compte.</small></label>
<label>Téléphone<input type="tel" name="phone" value="{{ old('phone',$user->profile?->phone) }}" maxlength="30" autocomplete="tel"></label>
<label>Biographie<textarea name="bio" maxlength="2000" rows="5">{{ old('bio',$user->profile?->bio) }}</textarea></label>
<label>Langue<input type="text" name="locale" value="{{ old('locale',$user->profile?->locale) }}" maxlength="10"></label>
<label>Fuseau horaire<input type="text" name="timezone" value="{{ old('timezone',$user->profile?->timezone) }}" maxlength="64"></label>
<button class="btn btn-primary" type="submit">Enregistrer</button></form></section></div>
@endsection