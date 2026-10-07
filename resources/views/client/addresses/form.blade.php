@extends('layouts.client')
@section('title',$address ? 'Modifier une adresse' : 'Ajouter une adresse')
@section('content')
@push('head')@vite('resources/css/pages/client/addresses.css')@endpush
<div class="client-addresses-page"><header class="client-module__header"><div><p class="client-module__eyebrow">ADRESSES</p><h1>{{ $address ? 'Modifier une adresse' : 'Ajouter une adresse' }}</h1><p>Conservez une adresse complète pour vos futures demandes de service.</p></div></header>
@if($errors->any())<div class="client-panel address-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="client-panel"><form method="POST" action="{{ $address ? route('client.addresses.update',$address) : route('client.addresses.store') }}" class="address-form">@csrf @if($address) @method('PATCH') @endif
<label>Libellé<input name="label" value="{{ old('label',$address?->label) }}" required maxlength="80" placeholder="Maison, Bureau..."></label>
<label>Nom du destinataire<input name="recipient_name" value="{{ old('recipient_name',$address?->recipient_name) }}" required maxlength="120"></label>
<label>Téléphone<input name="contact_phone" value="{{ old('contact_phone',$address?->contact_phone) }}" maxlength="30"></label>
<label>Pays (code ISO 2 lettres)<input name="country_code" value="{{ old('country_code',$address?->country_code) }}" required maxlength="2" minlength="2" placeholder="CD"></label>
<label>Province<input name="province" value="{{ old('province',$address?->province) }}" required maxlength="120"></label>
<label>Ville<input name="city" value="{{ old('city',$address?->city) }}" required maxlength="120"></label>
<label>Commune<input name="commune" value="{{ old('commune',$address?->commune) }}" maxlength="120"></label>
<label>Quartier<input name="neighborhood" value="{{ old('neighborhood',$address?->neighborhood) }}" maxlength="120"></label>
<label>Adresse<input name="address_line_1" value="{{ old('address_line_1',$address?->address_line_1) }}" required maxlength="255"></label>
<label>Complément<input name="address_line_2" value="{{ old('address_line_2',$address?->address_line_2) }}" maxlength="255"></label>
<label>Point de repère<input name="landmark" value="{{ old('landmark',$address?->landmark) }}" maxlength="255"></label>
<label>Code postal<input name="postal_code" value="{{ old('postal_code',$address?->postal_code) }}" maxlength="30"></label>
<label>Latitude<input type="number" step="any" name="latitude" value="{{ old('latitude',$address?->latitude) }}"></label>
<label>Longitude<input type="number" step="any" name="longitude" value="{{ old('longitude',$address?->longitude) }}"></label>
<label class="checkbox-field"><input type="checkbox" name="is_default" value="1" @checked(old('is_default',$address?->is_default))> Définir comme adresse principale</label>
<button class="btn btn-primary" type="submit">Enregistrer</button></form></section></div>
@endsection