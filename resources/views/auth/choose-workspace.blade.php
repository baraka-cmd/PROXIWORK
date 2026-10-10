@extends('layouts.auth')

@section('title', 'Choisir un espace — PROXIWORK')
@section('meta_description', 'Choisissez l’espace PROXIWORK auquel vous souhaitez accéder.')

@section('auth_content')
    <section class="login-card" aria-labelledby="workspace-title">
        <div class="login-card__header">
            <span class="login-eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> VOTRE COMPTE</span>
            <h1 id="workspace-title">Quel espace souhaitez-vous ouvrir ?</h1>
            <p>Votre choix détermine la destination de navigation, pas vos permissions. Chaque action reste contrôlée par le serveur.</p>
        </div>

        @foreach ($workspaces as $workspace => $routeName)
            <form method="POST" action="{{ route('workspace.switch') }}" class="login-form">
                @csrf
                <input type="hidden" name="workspace" value="{{ $workspace }}">
                <button type="submit" class="login-submit">
                    @switch($workspace)
                        @case('client')
                            Ouvrir mon espace client
                            @break
                        @case('professional')
                            Ouvrir mon espace professionnel
                            @break
                        @case('admin')
                            Ouvrir mon espace d’administration autorisé
                            @break
                    @endswitch
                </button>
            </form>
        @endforeach
    </section>
@endsection
