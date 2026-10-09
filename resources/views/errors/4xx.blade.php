@extends('layouts.app')

@section('title', 'Requête impossible — '.config('app.name', 'PROXIWORK'))

@section('body')
    <main id="main-content" class="public-main">
        <div class="page-container error-page-container">
            <x-error-state
                status="{{ $exception->getStatusCode() }}"
                title="La requête n’a pas pu aboutir"
                description="La ressource demandée est indisponible ou vous n’avez pas l’autorisation nécessaire."
                icon="fa-solid fa-circle-exclamation"
            >
                <a class="button button--primary" href="{{ url('/') }}">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Retour à l’accueil</span>
                </a>
                <button class="button button--ghost" type="button" onclick="window.history.back()">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Page précédente</span>
                </button>
            </x-error-state>
        </div>
    </main>
@endsection
