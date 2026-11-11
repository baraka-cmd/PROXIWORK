@extends('layouts.client')

@section('title', $address ? 'Modifier une adresse' : 'Ajouter une adresse')

@push('head')
    @vite(['resources/css/pages/client/addresses.css', 'resources/js/pages/client/addresses.js'])
@endpush

@section('content')
<div class="client-addresses-page">
    <header class="client-module__header address-page-header">
        <div>
            <p class="client-module__eyebrow">MES LIEUX D’INTERVENTION</p>
            <h1>{{ $address ? 'Modifier une adresse' : 'Ajouter une adresse' }}</h1>
            @if ($address)
                <p class="address-editing-label"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Adresse concernée : <strong>{{ $address->label }}</strong></p>
                <p>Modifiez les informations utiles pour vos prochaines demandes. L’historique des demandes et commandes reste inchangé.</p>
            @else
                <p>Enregistrez un lieu pour le retrouver lors de vos prochaines demandes. Les coordonnées GPS sont facultatives.</p>
            @endif
        </div>
        <a class="btn btn-secondary" href="{{ route('client.addresses.index') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à mes adresses
        </a>
    </header>

    @if ($errors->any())
        <div id="address-error-summary" class="client-panel address-alert address-alert--error address-error-summary" role="alert" aria-live="assertive" tabindex="-1" data-error-summary>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Vérifiez les informations signalées avant d’enregistrer.</strong>
                <p>Choisissez une erreur pour accéder au champ concerné.</p>
                <ul>
                    @foreach ($errors->getMessages() as $field => $messages)
                        @foreach ($messages as $message)
                            <li><a href="#{{ $field }}">{{ $message }}</a></li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="client-panel address-form-panel" aria-label="{{ $address ? 'Modifier les informations de l’adresse' : 'Formulaire d’ajout d’une adresse' }}">
        <form method="POST" action="{{ $address ? route('client.addresses.update', $address) : route('client.addresses.store') }}" class="address-form" data-address-form data-submit-label="{{ $address ? 'Enregistrement des modifications…' : 'Enregistrement de l’adresse…' }}">
            @csrf
            @if ($address) @method('PATCH') @endif

            <p class="address-required-note"><span aria-hidden="true">*</span> Les champs marqués d’un astérisque sont obligatoires.</p>

            <fieldset class="address-form-group">
                <legend><span class="address-form-group__number">01</span><span><strong>Identification du lieu</strong><small>Donnez un nom facile à reconnaître.</small></span></legend>
                <div class="address-form-grid">
                    <div class="address-field address-field--full">
                        <label for="label">Nom de l’adresse <span aria-hidden="true">*</span></label>
                        <input id="label" name="label" value="{{ old('label', $address?->label) }}" required maxlength="100" autocomplete="off" placeholder="Maison, Bureau, Chantier…" @error('label') aria-invalid="true" aria-describedby="label-error" @enderror>
                        @error('label')<small id="label-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="address-form-group">
                <legend><span class="address-form-group__number">02</span><span><strong>Adresse et localisation</strong><small>Indiquez où le professionnel doit se rendre.</small></span></legend>
                <div class="address-form-grid">
                    <div class="address-field">
                        <label for="country_code">Pays — code ISO à 2 lettres <span aria-hidden="true">*</span></label>
                        <input id="country_code" name="country_code" value="{{ old('country_code', $address?->country_code ?? 'CD') }}" required minlength="2" maxlength="2" pattern="[A-Za-z]{2}" autocomplete="country" placeholder="CD" aria-describedby="country-code-help @error('country_code') country-code-error @enderror" @error('country_code') aria-invalid="true" @enderror>
                        <small id="country-code-help">Saisissez le code pays, par exemple CD pour la République démocratique du Congo.</small>
                        @error('country_code')<small id="country-code-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="province">Province / région <span class="address-optional">(facultatif)</span></label>
                        <input id="province" name="province" value="{{ old('province', $address?->province) }}" maxlength="100" autocomplete="address-level1" placeholder="Nord-Kivu" @error('province') aria-invalid="true" aria-describedby="province-error" @enderror>
                        @error('province')<small id="province-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="city">Ville <span aria-hidden="true">*</span></label>
                        <input id="city" name="city" value="{{ old('city', $address?->city) }}" required maxlength="100" autocomplete="address-level2" placeholder="Goma" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
                        @error('city')<small id="city-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="commune">Commune <span class="address-optional">(facultatif)</span></label>
                        <input id="commune" name="commune" value="{{ old('commune', $address?->commune) }}" maxlength="100" placeholder="Ex. : commune ou secteur" @error('commune') aria-invalid="true" aria-describedby="commune-error" @enderror>
                        @error('commune')<small id="commune-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="neighborhood">Quartier <span class="address-optional">(facultatif)</span></label>
                        <input id="neighborhood" name="neighborhood" value="{{ old('neighborhood', $address?->neighborhood) }}" maxlength="150" placeholder="Nom du quartier" @error('neighborhood') aria-invalid="true" aria-describedby="neighborhood-error" @enderror>
                        @error('neighborhood')<small id="neighborhood-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field address-field--full">
                        <label for="address_line_1">Avenue / rue et numéro <span aria-hidden="true">*</span></label>
                        <input id="address_line_1" name="address_line_1" value="{{ old('address_line_1', $address?->address_line_1) }}" required maxlength="255" autocomplete="street-address" placeholder="Avenue, rue, numéro de parcelle…" @error('address_line_1') aria-invalid="true" aria-describedby="address-line-1-error" @enderror>
                        @error('address_line_1')<small id="address-line-1-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field address-field--full">
                        <label for="address_line_2">Complément d’adresse <span class="address-optional">(facultatif)</span></label>
                        <input id="address_line_2" name="address_line_2" value="{{ old('address_line_2', $address?->address_line_2) }}" maxlength="255" placeholder="Bâtiment, étage, porte…" @error('address_line_2') aria-invalid="true" aria-describedby="address-line-2-error" @enderror>
                        @error('address_line_2')<small id="address-line-2-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="landmark">Point de repère <span class="address-optional">(facultatif)</span></label>
                        <input id="landmark" name="landmark" value="{{ old('landmark', $address?->landmark) }}" maxlength="255" placeholder="École, marché, bâtiment connu…" @error('landmark') aria-invalid="true" aria-describedby="landmark-error" @enderror>
                        @error('landmark')<small id="landmark-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="postal_code">Code postal <span class="address-optional">(facultatif)</span></label>
                        <input id="postal_code" name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}" maxlength="32" autocomplete="postal-code" placeholder="Facultatif" @error('postal_code') aria-invalid="true" aria-describedby="postal-code-error" @enderror>
                        @error('postal_code')<small id="postal-code-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="address-form-group">
                <legend><span class="address-form-group__number">03</span><span><strong>Contact et instructions d’accès</strong><small>Aidez le professionnel à vous joindre et à trouver l’entrée.</small></span></legend>
                <div class="address-form-grid">
                    <div class="address-field">
                        <label for="recipient_name">Nom du contact sur place <span class="address-optional">(facultatif)</span></label>
                        <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $address?->recipient_name) }}" maxlength="255" autocomplete="name" placeholder="Nom de la personne à contacter" @error('recipient_name') aria-invalid="true" aria-describedby="recipient-name-error" @enderror>
                        @error('recipient_name')<small id="recipient-name-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field">
                        <label for="contact_phone">Téléphone du lieu <span class="address-optional">(facultatif)</span></label>
                        <input id="contact_phone" name="contact_phone" type="tel" value="{{ old('contact_phone', $address?->contact_phone) }}" maxlength="30" autocomplete="tel" placeholder="+243…" aria-describedby="contact-phone-help @error('contact_phone') contact-phone-error @enderror" @error('contact_phone') aria-invalid="true" @enderror>
                        <small id="contact-phone-help">Ce numéro peut être différent de celui de votre profil.</small>
                        @error('contact_phone')<small id="contact-phone-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="address-field address-field--full">
                        <label for="access_instructions">Instructions d’accès <span class="address-optional">(facultatif)</span></label>
                        <textarea id="access_instructions" name="access_instructions" rows="4" maxlength="5000" aria-describedby="access-instructions-help @error('access_instructions') access-instructions-error @enderror" placeholder="Ex. : portail bleu, appeler à l’arrivée, entrée derrière le bâtiment…" @error('access_instructions') aria-invalid="true" @enderror>{{ old('access_instructions', $address?->access_instructions) }}</textarea>
                        <small id="access-instructions-help">Maximum 5 000 caractères. Ces informations peuvent être conservées avec les demandes et commandes associées à ce lieu.</small>
                        @error('access_instructions')<small id="access-instructions-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="address-form-group address-form-group--gps">
                <legend><span class="address-form-group__number">04</span><span><strong>Coordonnées GPS</strong><small>Facultatives — ne les renseignez que si vous disposez de coordonnées fiables.</small></span></legend>
                <p class="address-gps-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> La latitude et la longitude doivent être renseignées ensemble. Aucune géolocalisation automatique n’est lancée.</p>
                <div class="address-form-grid">
                    <div class="address-field">
                        <label for="latitude">Latitude <span class="address-optional">(facultatif)</span></label>
                        <input id="latitude" type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude', $address?->latitude) }}" inputmode="decimal" placeholder="-1.68" aria-describedby="@error('latitude') latitude-error @enderror" @error('latitude') aria-invalid="true" @enderror>
                        @error('latitude')<small id="latitude-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="address-field">
                        <label for="longitude">Longitude <span class="address-optional">(facultatif)</span></label>
                        <input id="longitude" type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude', $address?->longitude) }}" inputmode="decimal" placeholder="29.23" aria-describedby="@error('longitude') longitude-error @enderror" @error('longitude') aria-invalid="true" @enderror>
                        @error('longitude')<small id="longitude-error" class="address-field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="address-form-group address-form-group--preferences">
                <legend><span class="address-form-group__number">05</span><span><strong>Préférences</strong><small>Choisissez comment cette adresse sera proposée.</small></span></legend>
                <div class="address-default-control">
                    <input type="hidden" name="is_default" value="0">
                    <input id="is_default" type="checkbox" name="is_default" value="1" @checked((bool) old('is_default', $address?->is_default))>
                    <label for="is_default"><strong>Utiliser cette adresse par défaut pour mes prochaines demandes</strong><span>Vous pourrez choisir une autre adresse par défaut ultérieurement. Vos anciennes demandes et commandes conservent leur propre historique.</span></label>
                </div>
            </fieldset>

            <div class="address-form-actions">
                <a class="btn btn-secondary" href="{{ route('client.addresses.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit" data-submit-label="{{ $address ? 'Enregistrement des modifications…' : 'Enregistrement de l’adresse…' }}"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $address ? 'Enregistrer les modifications' : 'Enregistrer l’adresse' }}</button>
            </div>
        </form>
    </section>
</div>
@endsection
