@extends('layouts.client')
@section('title',$address ? 'Modifier une adresse' : 'Ajouter une adresse')
@section('content')
@push('head')@vite('resources/css/pages/client/addresses.css')@endpush
<div class="client-addresses-page"><header class="client-module__header"><div><p class="client-module__eyebrow">ADRESSES</p><h1>{{ $address ? 'Modifier une adresse' : 'Ajouter une adresse' }}</h1></div></header>
<section class="client-panel"><form method="POST" action="{{ $address ? route('client.addresses.update',$address) : route('client.addresses.store') }}" class="address-form">@csrf @if($address) @method('PATCH') @endif
<label>Libellé<input name="label" value="{{ old('label',$address?->label) }}" required maxlength="80" placeholder="Maison, Bureau..."></label>
<label>Nom du destinataire<input name="recipient_name" value="{{ old('recipient_name',$address?->recipient_name) }}" required maxlength="120"></label>
<label>Téléphone<input name="phone" value="{{ old('phone',$address?->phone) }}" maxlength="30"></label>
<label>Adresse<input name="address_line_1" value="{{ old('address_line_1',$address?->address_line_1) }}" required maxlength="255"></label>
<label>Complément<input name="address_line_2" value="{{ old('address_line_2',$address?->address_line_2) }}" maxlength="255"></label>
<label>Quartier<input name="neighborhood" value="{{ old('neighborhood',$address?->neighborhood) }}" maxlength="120"></label>
<label>Commune<input name="commune" value="{{ old('commune',$address?->commune) }}" maxlength="120"></label>
<label>Ville<input name="city" value="{{ old('city',$address?->city) }}" required maxlength="120"></label>
<label>Province<input name="province" value="{{ old('province',$address?->province) }}" required maxlength="120"></label>
<label>Pays<input name="country" value="{{ old('country',$address?->country) }}" required maxlength="120"></label>
<label class="checkbox-field"><input type="checkbox" name="is_default" value="1" @checked(old('is_default',$address?->is_default))> Définir comme adresse principale</label>
<button class="btn btn-primary" type="submit">Enregistrer</button></form></section></div>
@endsection