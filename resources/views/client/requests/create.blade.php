@extends('layouts.client')

@section('title', 'Nouvelle demande')

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
@endpush

@section('content')
@php($pricingLabels = ['fixed' => 'Prix fixe', 'range' => 'Fourchette de prix', 'from' => 'À partir de', 'quote' => 'Sur devis'])
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
        <div class="client-form-alert client-form-alert--summary" role="alert" tabindex="-1" data-error-summary>
            <strong>Quelques informations doivent être corrigées avant d’enregistrer le brouillon.</strong>
            <ul>
                @foreach($errors->getMessages() as $field => $messages)
                    @foreach($messages as $message)
                        <li><a href="{{ in_array($field, ['service_id', 'idempotency_key'], true) ? '#request-form' : '#'.$field }}">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
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
                <div><dt>Mode d’exécution</dt><dd>{{ $service->execution_mode->label() }}</dd></div>
                <div><dt>Tarification</dt><dd>{{ $pricingLabels[$service->pricing_type->value] ?? 'Tarification à confirmer' }}</dd></div>
                <div><dt>Devise</dt><dd>{{ $service->currency }}</dd></div>
                <div><dt>Durée</dt><dd>{{ $service->estimated_duration_minutes ? $service->estimated_duration_minutes.' min' : 'À définir' }}</dd></div>
            </dl>
        </aside>

        <form id="request-form" class="client-panel client-request-form" method="POST" action="{{ route('client.requests.store') }}">
            @csrf
            <input type="hidden" name="service_id" value="{{ $service->id }}">
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

            <div class="client-form-section">
                <p class="client-form-section__title"><span class="client-form-step">01</span> Votre besoin</p>
                <div class="client-form-grid">
                    <div class="client-field client-form-grid__full">
                        <label for="title">Titre de la demande <span>*</span></label>
                        <input id="title" name="title" value="{{ old('title') }}" maxlength="160" required aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" @if($errors->has('title')) aria-describedby="title-error" @endif placeholder="Ex. Développer une application de gestion">
                        @error('title')<small id="title-error" class="client-field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="client-field client-form-grid__full">
                        <label for="description">Description détaillée <span>*</span></label>
                        <textarea id="description" name="description" rows="8" minlength="20" maxlength="10000" required @if($errors->has('description')) aria-describedby="description-hint description-error" @else aria-describedby="description-hint" @endif aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" placeholder="Expliquez le contexte, vos objectifs, les fonctionnalités attendues et toute contrainte importante...">{{ old('description') }}</textarea>
                        <small id="description-hint" class="client-field-hint">Décrivez le résultat attendu, le contexte, les contraintes et les éléments à prendre en compte. 20 à 10 000 caractères.</small>
                        @error('description')<small id="description-error" class="client-field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="client-form-section">
                <p class="client-form-section__title"><span class="client-form-step">02</span> Budget et délai</p>
                <div class="client-form-grid">
                    <div class="client-field"><label for="budget_min">Budget minimum <span class="client-field__optional">Facultatif</span></label><input id="budget_min" name="budget_min" type="number" min="0" step="0.01" value="{{ old('budget_min') }}" aria-describedby="budget-hint" aria-invalid="{{ $errors->has('budget_min') ? 'true' : 'false' }}" placeholder="0,00">@error('budget_min')<small id="budget_min-error" class="client-field-error">{{ $message }}</small>@enderror</div>
                    <div class="client-field"><label for="budget_max">Budget maximum <span class="client-field__optional">Facultatif</span></label><input id="budget_max" name="budget_max" type="number" min="0" step="0.01" value="{{ old('budget_max') }}" aria-describedby="budget-hint" placeholder="0,00">@error('budget_max')<small id="budget_max-error" class="client-field-error">{{ $message }}</small>@enderror</div>
                    <div class="client-field"><label for="currency">Devise du budget</label><input id="currency" name="currency" maxlength="3" value="{{ old('currency', $service->currency ?: 'USD') }}" aria-describedby="budget-hint" placeholder="USD">@error('currency')<small id="currency-error" class="client-field-error">{{ $message }}</small>@enderror</div>
                    <div class="client-field"><label for="desired_at">Date souhaitée <span class="client-field__optional">Facultatif</span></label><input id="desired_at" name="desired_at" type="datetime-local" value="{{ old('desired_at') }}">@error('desired_at')<small id="desired_at-error" class="client-field-error">{{ $message }}</small>@enderror</div>
                    <p id="budget-hint" class="client-field-hint client-form-grid__full">Le budget est une indication pour le professionnel, pas un prix définitif. Le montant contractuel sera celui du devis accepté. Si vous renseignez un budget, indiquez une devise.</p>
                </div>
            </div>

            <div class="client-form-section">
                <p class="client-form-section__title"><span class="client-form-step">03</span> Lieu d’intervention</p>
                @if($service->execution_mode->requiresClientAddress())
                @if($addresses->isEmpty())
                    <div class="client-form-note"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Aucune adresse enregistrée. Vous pouvez sauvegarder ce brouillon maintenant, mais vous devrez ajouter une adresse avant de l’envoyer au professionnel.</span></div>
                @else
                    <div class="client-address-options">
                        <label class="client-address-option"><input id="address_id" type="radio" name="address_id" value="" @checked((string) old('address_id', '') === '')><span><strong>À définir</strong><small>Vous pourrez choisir une adresse avant l’envoi au professionnel.</small></span></label>
                        @foreach($addresses as $address)
                            <label class="client-address-option"><input type="radio" name="address_id" value="{{ $address->id }}" @checked((string) old('address_id', $address->is_default ? $address->id : '') === (string) $address->id)><span><strong>{{ $address->label }}</strong><small>{{ $address->city }}{{ $address->province ? ', '.$address->province : '' }} · {{ $address->address_line_1 }}</small></span></label>
                        @endforeach
                    </div>
                @endif
                @error('address_id')<small id="address_id-error" class="client-field-error">{{ $message }}</small>@enderror
                @else
                    <div class="client-form-note"><i class="fa-solid fa-laptop" aria-hidden="true"></i><span>Cette prestation est proposée à distance. Aucune adresse physique ne sera demandée ni jointe à la demande ou à la commande.</span></div>
                @endif
            </div>

            <div class="client-form-note client-form-note--draft"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i><span>Cette étape enregistre un brouillon privé. Le professionnel ne reçoit pas encore la demande ; vous l’enverrez depuis la page de détail après vérification.</span></div>
            <div class="client-form-actions">
                <a class="btn btn-secondary" href="{{ route('client.requests.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer le brouillon</button>
            </div>
        </form>
    </div>
</div>
@endsection
