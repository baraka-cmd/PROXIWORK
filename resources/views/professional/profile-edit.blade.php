@extends('layouts.professional')

@section('title', 'Modifier mon profil professionnel — PROXIWORK')
@section('page_heading', 'Modifier mon profil professionnel')

@section('content')
<div class="dashboard-page-header">
    <div>
        <p class="eyebrow">Votre présence sur PROXIWORK</p>
        <h1>Modifier votre profil</h1>
        <p class="page-lead">Décrivez votre expertise et votre zone de service. Votre adresse personnelle reste séparée de votre zone professionnelle.</p>
    </div>
    <div class="dashboard-page-header__actions">
        <a class="button button--ghost" href="{{ route('professional.profile') }}">Retour au profil</a>
    </div>
</div>

<section class="surface-card surface-card--lg">
    <div class="surface-card__body">
        <form method="POST" action="{{ route('professional.profile.update') }}" class="form-stack">
            @csrf
            @method('PATCH')

            <div class="form-grid">
                <div class="form-field form-field--full">
                    <label for="professional_title">Titre professionnel</label>
                    <input id="professional_title" name="professional_title" type="text" maxlength="160" value="{{ old('professional_title', $professional->professional_title) }}" autocomplete="organization-title">
                    @error('professional_title')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field form-field--full">
                    <label for="description">Présentation professionnelle</label>
                    <textarea id="description" name="description" rows="6" maxlength="5000">{{ old('description', $professional->description) }}</textarea>
                    @error('description')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="years_experience">Années d’expérience</label>
                    <input id="years_experience" name="years_experience" type="number" min="0" max="80" value="{{ old('years_experience', $professional->years_experience) }}">
                    @error('years_experience')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="starting_price">Prix indicatif de départ</label>
                    <input id="starting_price" name="starting_price" type="number" min="0" step="0.01" value="{{ old('starting_price', $professional->starting_price) }}">
                    @error('starting_price')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="currency">Devise du prix indicatif</label>
                    <select id="currency" name="currency">
                        <option value="">Choisir une devise</option>
                        @foreach(['USD' => 'USD — Dollar américain', 'CDF' => 'CDF — Franc congolais', 'EUR' => 'EUR — Euro'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency', $professional->currency) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('currency')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <fieldset class="form-fieldset">
                <legend>Zone de service</legend>
                <p class="field-help">Ces informations décrivent la zone dans laquelle vous travaillez, et non votre adresse personnelle.</p>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="province">Province</label>
                        <input id="province" name="province" type="text" maxlength="100" value="{{ old('province', $professional->province) }}">
                        @error('province')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-field">
                        <label for="city">Ville</label>
                        <input id="city" name="city" type="text" maxlength="100" value="{{ old('city', $professional->city) }}">
                        @error('city')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-field">
                        <label for="commune">Commune / secteur</label>
                        <input id="commune" name="commune" type="text" maxlength="100" value="{{ old('commune', $professional->commune) }}">
                        @error('commune')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-field">
                        <label for="service_radius_km">Rayon de service (km)</label>
                        <input id="service_radius_km" name="service_radius_km" type="number" min="1" max="500" value="{{ old('service_radius_km', $professional->service_radius_km) }}">
                        @error('service_radius_km')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-field">
                        <label for="latitude">Latitude (facultative)</label>
                        <input id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $professional->latitude) }}">
                        @error('latitude')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-field">
                        <label for="longitude">Longitude (facultative)</label>
                        <input id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $professional->longitude) }}">
                        @error('longitude')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <a class="button button--ghost" href="{{ route('professional.profile') }}">Annuler</a>
                <button class="button button--primary" type="submit">Enregistrer le profil</button>
            </div>
        </form>
    </div>
</section>
@endsection
