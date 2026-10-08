@extends('layouts.client')

@section('title', 'Nouvelle demande')

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
@endpush

@section('content')
<div class="client-module client-request-form-page">
    <div class="client-module__header">
        <div>
            <p class="client-module__eyebrow">NOUVELLE DEMANDE</p>
            <h1>Décrivez votre besoin</h1>
            <p>Préparez votre demande avec les informations utiles au professionnel.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('public.professionals.index') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour
        </a>
    </div>

    @if($errors->any())
        <div class="client-form-alert" role="alert">
            <strong>Vérifiez les informations saisies.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="client-request-layout">
        <aside class="client-panel client-request-summary">
            <p class="client-module__eyebrow">SERVICE SÉLECTIONNÉ</p>
            <h2>{{ $service->title }}</h2>
            <p>{{ $service->short_description }}</p>
            <div class="client-request-professional">
                <div class="client-avatar">{{ strtoupper(substr($service->professionalProfile?->user?->name ?? 'P', 0, 1)) }}</div>
                <div><strong>{{ $service->professionalProfile?->user?->name }}</strong><span>{{ $service->professionalProfile?->professional_title }}</span></div>
            </div>
            <dl class="client-definition-list">
                <div><dt>Tarification</dt><dd>{{ ucfirst($service->pricing_type->value) }}</dd></div>
                <div><dt>Devise</dt><dd>{{ $service->currency }}</dd></div>
                <div><dt>Durée</dt><dd>{{ $service->estimated_duration_minutes ? $service->estimated_duration_minutes.' min' : 'À définir' }}</dd></div>
            </dl>
        </aside>

        <form class="client-panel client-request-form" method="POST" action="{{ route('client.requests.store') }}">
            @csrf
            <input type="hidden" name="service_id" value="{{ $service->id }}">

            <div class="client-form-section">
                <p class="client-form-section__title">1. Votre besoin</p>
                <div class="client-form-grid">
                    <div class="client-field client-form-grid__full">
                        <label for="title">Titre de la demande <span>*</span></label>
                        <input id="title" name="title" value="{{ old('title') }}" maxlength="160" required placeholder="Ex. Développer une application de gestion">
                        @error('title')<small>{{ $message }}</small>@enderror
                    </div>
                    <div class="client-field client-form-grid__full">
                        <label for="description">Description détaillée <span>*</span></label>
                        <textarea id="description" name="description" rows="8" minlength="20" maxlength="10000" required placeholder="Expliquez le contexte, vos objectifs, les fonctionnalités attendues et toute contrainte importante...">{{ old('description') }}</textarea>
                        @error('description')<small>{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="client-form-section">
                <p class="client-form-section__title">2. Budget et délai</p>
                <div class="client-form-grid">
                    <div class="client-field"><label for="budget_min">Budget minimum</label><input id="budget_min" name="budget_min" type="number" min="0" step="0.01" value="{{ old('budget_min') }}" placeholder="0.00"></div>
                    <div class="client-field"><label for="budget_max">Budget maximum</label><input id="budget_max" name="budget_max" type="number" min="0" step="0.01" value="{{ old('budget_max') }}" placeholder="0.00"></div>
                    <div class="client-field"><label for="currency">Devise</label><input id="currency" name="currency" maxlength="3" value="{{ old('currency', $service->currency ?: 'USD') }}" placeholder="USD"></div>
                    <div class="client-field"><label for="desired_at">Date souhaitée</label><input id="desired_at" name="desired_at" type="datetime-local" value="{{ old('desired_at') }}"></div>
                </div>
            </div>

            <div class="client-form-section">
                <p class="client-form-section__title">3. Lieu d'intervention</p>
                @if($addresses->isEmpty())
                    <div class="client-form-note"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Aucune adresse enregistrée. Vous pouvez continuer sans adresse et en ajouter une depuis votre espace.</span></div>
                @else
                    <div class="client-address-options">
                        <label class="client-address-option"><input type="radio" name="address_id" value=""><span><strong>À définir</strong><small>Je préciserai le lieu plus tard.</small></span></label>
                        @foreach($addresses as $address)
                            <label class="client-address-option"><input type="radio" name="address_id" value="{{ $address->id }}" {{ $address->is_default ? 'checked' : '' }}><span><strong>{{ $address->label }}</strong><small>{{ $address->city }}{{ $address->province ? ', '.$address->province : '' }} · {{ $address->address_line_1 }}</small></span></label>
                        @endforeach
                    </div>
                @endif
                @error('address_id')<small class="client-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="client-form-actions">
                <a class="btn btn-secondary" href="{{ route('client.requests.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer le brouillon</button>
            </div>
        </form>
    </div>
</div>
@endsection
