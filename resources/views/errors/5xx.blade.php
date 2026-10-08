@extends('layouts.app')

@section('title', 'Service indisponible — '.config('app.name', 'PROXIWORK'))

@section('body')
    <main id="main-content" class="public-main">
        <div class="page-container" style="padding-block: var(--space-12);">
            <x-error-state
                status="{{ $exception->getStatusCode() }}"
                title="Le service est momentanément indisponible"
                description="Une erreur technique empêche momentanément l’affichage de cette page. Réessayez dans quelques instants."
                icon="fa-solid fa-server"
            >
                <button class="button button--primary" type="button" onclick="window.location.reload()">
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                    <span>Réessayer</span>
                </button>
                <a class="button button--ghost" href="{{ url('/') }}">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Retour à l’accueil</span>
                </a>
            </x-error-state>
        </div>
    </main>
@endsection
