@extends('layouts.auth')

@section('title', 'Choisir un compte — PROXIWORK')
@section('meta_description', 'Choisissez votre parcours PROXIWORK : trouver un professionnel ou proposer vos services.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
    <div class="register-page register-choice-page">
        <section class="register-card" aria-labelledby="register-title">
            <div class="register-card__header">
                <span class="register-eyebrow"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> CRÉER UN COMPTE</span>
                <h1 id="register-title">Quel est votre besoin ?</h1>
                <p>Choisissez votre espace. Vous pourrez ensuite compléter les informations utiles à votre activité.</p>
            </div>

            <div class="register-choice-grid">
                <a class="register-choice-card" href="{{ route('register.client') }}">
                    <span class="register-choice-card__icon"><i class="fa-solid fa-compass" aria-hidden="true"></i></span>
                    <span class="register-choice-card__eyebrow">CÔTÉ CLIENT</span>
                    <strong>Je recherche un professionnel</strong>
                    <span>Explorez les services, envoyez une demande et suivez vos projets.</span>
                    <span class="register-choice-card__action">Créer un compte client <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>

                <a class="register-choice-card register-choice-card--professional" href="{{ route('register.professional') }}">
                    <span class="register-choice-card__icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>
                    <span class="register-choice-card__eyebrow">CÔTÉ PROFESSIONNEL</span>
                    <strong>Je souhaite proposer mes services</strong>
                    <span>Présentez vos compétences, vos prestations et vos justificatifs.</span>
                    <span class="register-choice-card__action">Démarrer mon dossier <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>

            <p class="register-login">Vous avez déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
        </section>

    </div>
@endsection
