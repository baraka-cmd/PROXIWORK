@extends('layouts.app')

@section('title', 'Requête impossible — '.config('app.name', 'PROXIWORK'))

@section('body')
    @php
        $isEmailConfirmationUnavailable = $exception->getStatusCode() === 403
            && str_contains($exception->getMessage(), 'Ce lien de confirmation est invalide ou a expiré.');
        $errorRecoveryUrl = $isEmailConfirmationUnavailable && auth()->check()
            ? route('client.profile.edit').'#profile-email'
            : url('/');
    @endphp
    <main id="main-content" class="public-main">
        <div class="page-container error-page-container">
            <x-error-state
                status="{{ $exception->getStatusCode() }}"
                title="{{ $isEmailConfirmationUnavailable ? 'Ce lien de confirmation n’est plus valide' : 'La requête n’a pas pu aboutir' }}"
                description="{{ $isEmailConfirmationUnavailable ? 'Le lien est invalide ou a expiré. Votre adresse de connexion actuelle n’a pas été modifiée. Revenez aux paramètres du profil pour demander un nouveau lien.' : 'La ressource demandée est indisponible ou vous n’avez pas l’autorisation nécessaire.' }}"
                icon="{{ $isEmailConfirmationUnavailable ? 'fa-solid fa-envelope-circle-check' : 'fa-solid fa-circle-exclamation' }}"
            >
                <a class="button button--primary" href="{{ $errorRecoveryUrl }}">
                    <i class="{{ $isEmailConfirmationUnavailable ? 'fa-solid fa-envelope' : 'fa-solid fa-house' }}" aria-hidden="true"></i>
                    <span>{{ $isEmailConfirmationUnavailable ? 'Retour aux paramètres e-mail' : 'Retour à l’accueil' }}</span>
                </a>
                <button class="button button--ghost" type="button" onclick="window.history.back()">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Page précédente</span>
                </button>
            </x-error-state>
        </div>
    </main>
@endsection
