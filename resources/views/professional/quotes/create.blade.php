@extends('layouts.professional')

@section('title', 'Créer un devis — PROXIWORK')
@section('page_heading', 'Créer un devis')

@push('head')
    @vite('resources/css/pages/professional/quotes.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/professional/quote-form.js')
@endpush

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Demande #{{ $serviceRequest->getKey() }}</p>
            <h1>Préparer un devis</h1>
            <p class="page-lead">{{ $serviceRequest->title }} · {{ $serviceRequest->client?->name ?? 'Client' }}</p>
        </div>
        <div class="dashboard-page-header__actions"><a class="button button--ghost" href="{{ route('professional.requests.show', $serviceRequest) }}">Retour à la demande</a></div>
    </div>

    <section class="professional-quote-request-context surface-card" aria-label="Rappel de la demande">
        <div><span>Demande concernée</span><strong>{{ $serviceRequest->title }}</strong><small>Référence PW-REQ-{{ str_pad((string) $serviceRequest->getKey(), 6, '0', STR_PAD_LEFT) }}</small></div>
        <div><span>Client</span><strong>{{ $serviceRequest->client?->name ?? 'Client' }}</strong></div>
        <div><span>Budget indiqué</span><strong>
            @if ($serviceRequest->budget_min !== null || $serviceRequest->budget_max !== null)
                {{ $serviceRequest->budget_min !== null ? number_format((float) $serviceRequest->budget_min, 2, ',', ' ') : '—' }}
                @if ($serviceRequest->budget_min !== null && $serviceRequest->budget_max !== null) – @endif
                {{ $serviceRequest->budget_max !== null ? number_format((float) $serviceRequest->budget_max, 2, ',', ' ') : '' }}
                {{ $serviceRequest->currency ?? '' }}
            @else
                Non précisé
            @endif
        </strong></div>
    </section>

    <form method="POST" action="{{ route('professional.quotes.store', $serviceRequest) }}" class="surface-card form-card professional-quote-create-form" data-quote-form>
        @csrf
        <div class="form-grid">
            <div class="form-field">
                <label class="form-label" for="amount">Montant proposé</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" value="{{ old('amount') }}" required aria-describedby="amount-help">
                <small id="amount-help" class="professional-quote-field-help">Indiquez le montant contractuel proposé dans la devise choisie. Aucun frais ou calcul supplémentaire n’est ajouté par ce formulaire.</small>
                @error('amount')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label class="form-label" for="currency">Devise (ISO 4217)</label>
                <input class="form-control" id="currency" name="currency" type="text" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" value="{{ old('currency', 'CDF') }}" required>
                @error('currency')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label class="form-label" for="duration_value">Délai estimé</label>
                <input class="form-control" id="duration_value" name="duration_value" type="number" min="1" max="10000" value="{{ old('duration_value', 1) }}" required>
                @error('duration_value')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label class="form-label" for="duration_unit">Unité du délai</label>
                <select class="form-control form-select" id="duration_unit" name="duration_unit" required>
                    @foreach (\App\Enums\QuotationDurationUnit::cases() as $unit)
                        <option value="{{ $unit->value }}" @selected(old('duration_unit', 'days') === $unit->value)>{{ match($unit->value) { 'hours' => 'Heures', 'days' => 'Jours', 'weeks' => 'Semaines', default => ucfirst($unit->value) } }}</option>
                    @endforeach
                </select>
                @error('duration_unit')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field form-field--full">
                <label class="form-label" for="description">Description détaillée</label>
                <textarea class="form-control" id="description" name="description" rows="5" minlength="10" maxlength="10000" required>{{ old('description') }}</textarea>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field form-field--full">
                <label class="form-label" for="conditions">Conditions (facultatif)</label>
                <textarea class="form-control" id="conditions" name="conditions" rows="3" maxlength="10000">{{ old('conditions') }}</textarea>
                @error('conditions')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label class="form-label" for="valid_until">Valable jusqu'au</label>
                <input class="form-control" id="valid_until" name="valid_until" type="datetime-local" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" value="{{ old('valid_until') }}" required>
                @error('valid_until')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
        @if ($errors->has('service_request'))
            <div class="alert alert--danger" role="alert">{{ $errors->first('service_request') }}</div>
        @endif
        <section class="professional-quote-review" aria-labelledby="quote-review-title" aria-live="polite">
            <div><span class="eyebrow">RÉCAPITULATIF</span><h2 id="quote-review-title">Vérifiez votre proposition</h2></div>
            <dl>
                <div><dt>Montant</dt><dd data-quote-summary-amount>À renseigner</dd></div>
                <div><dt>Délai</dt><dd data-quote-summary-duration>À renseigner</dd></div>
                <div><dt>Validité</dt><dd data-quote-summary-validity>À renseigner</dd></div>
            </dl>
            <p>Ce récapitulatif est une aide visuelle. Le serveur valide et enregistre les conditions définitives du devis.</p>
        </section>
        <div class="form-actions">
            <a class="button button--ghost" href="{{ route('professional.requests.show', $serviceRequest) }}">Annuler</a>
            <button class="button button--primary" type="submit">Envoyer le devis</button>
        </div>
    </form>
@endsection
