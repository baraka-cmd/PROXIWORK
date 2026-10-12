@extends('layouts.client')

@section('title', $address ? 'Modifier une adresse' : 'Ajouter une adresse')

@push('head')
    @vite('resources/css/pages/client/addresses.css')
@endpush

@section('content')
<div class="client-addresses-page">
    <header class="client-module__header address-page-header">
        <div>
            <p class="client-module__eyebrow">CARNET D’ADRESSES</p>
            <h1>{{ $address ? 'Modifier une adresse' : 'Ajouter une adresse' }}</h1>
            <p>Indiquez les informations qui aideront le professionnel à trouver le lieu. Les champs GPS sont facultatifs et doivent être renseignés ensemble.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('client.addresses.index') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour au carnet
        </a>
    </header>

    @if($errors->any())
        <div class="client-panel address-alert address-alert--error" role="alert" aria-live="assertive">
            <strong>Vérifiez les informations signalées avant d’enregistrer.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="client-panel address-form-panel">
        <form method="POST" action="{{ $address ? route('client.addresses.update', $address) : route('client.addresses.store') }}" class="address-form" novalidate>
            @csrf
            @if($address) @method('PATCH') @endif

            <div class="address-form-intro">
                <span class="address-form-intro__icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                <div><h2>Lieu d’intervention</h2><p>Les champs marqués d’un astérisque sont obligatoires.</p></div>
            </div>

            <div class="address-form-grid">
                <div class="address-field">
                    <label for="label">Nom de l’adresse <span>*</span></label>
                    <input id="label" name="label" value="{{ old('label', $address?->label) }}" required maxlength="100" autocomplete="off" placeholder="Maison, Bureau, Chantier…" @if($errors->has('label')) aria-invalid="true" aria-describedby="label-error" @endif>
                    @error('label')<small id="label-error" class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="recipient_name">Nom du contact sur place</label>
                    <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $address?->recipient_name) }}" maxlength="255" autocomplete="name" placeholder="Facultatif">
                    @error('recipient_name')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="contact_phone">Téléphone du lieu</label>
                    <input id="contact_phone" name="contact_phone" type="tel" value="{{ old('contact_phone', $address?->contact_phone) }}" maxlength="30" autocomplete="tel" placeholder="+243…" aria-describedby="contact-phone-help">
                    <small id="contact-phone-help">Facultatif. Ce numéro peut être différent de celui de votre profil.</small>
                    @error('contact_phone')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="country_code">Code pays ISO à 2 lettres <span>*</span></label>
                    <input id="country_code" name="country_code" value="{{ old('country_code', $address?->country_code ?? 'CD') }}" required minlength="2" maxlength="2" autocomplete="country" placeholder="CD">
                    @error('country_code')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="province">Province / région</label>
                    <input id="province" name="province" value="{{ old('province', $address?->province) }}" maxlength="100" autocomplete="address-level1" placeholder="Nord-Kivu">
                    @error('province')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="city">Ville <span>*</span></label>
                    <input id="city" name="city" value="{{ old('city', $address?->city) }}" required maxlength="100" autocomplete="address-level2" placeholder="Goma">
                    @error('city')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="commune">Commune</label>
                    <input id="commune" name="commune" value="{{ old('commune', $address?->commune) }}" maxlength="100" placeholder="Facultatif">
                    @error('commune')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="neighborhood">Quartier</label>
                    <input id="neighborhood" name="neighborhood" value="{{ old('neighborhood', $address?->neighborhood) }}" maxlength="150" placeholder="Himbi, Katindo…">
                    @error('neighborhood')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field address-field--full">
                    <label for="address_line_1">Avenue / rue et numéro <span>*</span></label>
                    <input id="address_line_1" name="address_line_1" value="{{ old('address_line_1', $address?->address_line_1) }}" required maxlength="255" autocomplete="street-address" placeholder="Avenue, numéro de parcelle…">
                    @error('address_line_1')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field address-field--full">
                    <label for="address_line_2">Complément d’adresse</label>
                    <input id="address_line_2" name="address_line_2" value="{{ old('address_line_2', $address?->address_line_2) }}" maxlength="255" placeholder="Bâtiment, étage, porte…">
                    @error('address_line_2')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field address-field--full">
                    <label for="landmark">Point de repère</label>
                    <input id="landmark" name="landmark" value="{{ old('landmark', $address?->landmark) }}" maxlength="255" placeholder="Église, école, marché ou bâtiment connu">
                    @error('landmark')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field address-field--full">
                    <label for="access_instructions">Instructions d’accès</label>
                    <textarea id="access_instructions" name="access_instructions" rows="4" maxlength="5000" placeholder="Ex. portail bleu, appeler à l’arrivée, entrée derrière le bâtiment…">{{ old('access_instructions', $address?->access_instructions) }}</textarea>
                    <small>Ces instructions seront conservées avec le lieu choisi pour une demande et avec l’instantané d’une commande.</small>
                    @error('access_instructions')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="postal_code">Code postal</label>
                    <input id="postal_code" name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}" maxlength="32" autocomplete="postal-code" placeholder="Facultatif">
                    @error('postal_code')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-form-subheading address-field--full">
                    <h3>Coordonnées GPS <span>Facultatif</span></h3>
                    <p>Ne saisissez ces valeurs que si vous disposez de coordonnées fiables. Latitude et longitude doivent être renseignées ensemble.</p>
                </div>

                <div class="address-field">
                    <label for="latitude">Latitude</label>
                    <input id="latitude" type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude', $address?->latitude) }}" inputmode="decimal" placeholder="-1.68">
                    @error('latitude')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="address-field">
                    <label for="longitude">Longitude</label>
                    <input id="longitude" type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude', $address?->longitude) }}" inputmode="decimal" placeholder="29.23">
                    @error('longitude')<small class="address-field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="address-default-control">
                <input type="hidden" name="is_default" value="0">
                <input id="is_default" type="checkbox" name="is_default" value="1" @checked((bool) old('is_default', $address?->is_default))>
                <label for="is_default"><strong>Définir comme adresse par défaut</strong><span>Cette adresse sera proposée en premier pour vos prochaines demandes. Vous pourrez toujours choisir un autre lieu.</span></label>
            </div>

            <div class="address-form-actions">
                <a class="btn btn-secondary" href="{{ route('client.addresses.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $address ? 'Enregistrer les modifications' : 'Enregistrer l’adresse' }}</button>
            </div>
        </form>
    </section>
</div>
@endsection
