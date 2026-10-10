@extends('layouts.professional')

@section('title', 'Créer un devis — PROXIWORK')
@section('page_heading', 'Créer un devis')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Demande #{{ $serviceRequest->getKey() }}</p>
            <h1>Préparer un devis</h1>
            <p class="page-lead">{{ $serviceRequest->title }} · {{ $serviceRequest->client?->name ?? 'Client' }}</p>
        </div>
        <div class="dashboard-page-header__actions"><a class="button button--ghost" href="{{ route('professional.requests.show', $serviceRequest) }}">Retour à la demande</a></div>
    </div>

    <form method="POST" action="{{ route('professional.quotes.store', $serviceRequest) }}" class="surface-card form-card">
        @csrf
        <div class="form-grid">
            <div class="form-field">
                <label class="form-label" for="amount">Montant proposé</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" value="{{ old('amount') }}" required>
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
                        <option value="{{ $unit->value }}" @selected(old('duration_unit', 'days') === $unit->value)>{{ ucfirst($unit->value) }}</option>
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
        <div class="form-actions">
            <a class="button button--ghost" href="{{ route('professional.requests.show', $serviceRequest) }}">Annuler</a>
            <button class="button button--primary" type="submit">Envoyer le devis</button>
        </div>
    </form>
@endsection
