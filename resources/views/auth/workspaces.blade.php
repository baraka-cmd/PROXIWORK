@extends('layouts.auth')

@section('title', 'Choisir un espace — PROXIWORK')
@section('meta_description', 'Choisissez l’espace PROXIWORK correspondant à votre activité.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
    <div class="register-page register-choice-page">
        <section class="register-card" aria-labelledby="workspace-title">
            <div class="register-card__header">
                <span class="register-eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> VOS ESPACES PROXIWORK</span>
                <h1 id="workspace-title">Dans quel espace souhaitez-vous entrer ?</h1>
                <p>Votre choix change la destination de navigation. Vos rôles et permissions restent contrôlés par le serveur.</p>
            </div>

            <div class="register-choice-grid">
                @foreach ($workspaces as $workspace)
                    <form method="POST" action="{{ route('workspace.switch', ['workspace' => $workspace['key']]) }}">
                        @csrf
                        <button class="register-choice-card" type="submit">
                            <span class="register-choice-card__icon"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i></span>
                            <strong>{{ $workspace['title'] }}</strong>
                            <span>{{ $workspace['description'] }}</span>
                            <span class="register-choice-card__action">Ouvrir cet espace <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
@endsection
