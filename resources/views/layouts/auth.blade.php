@extends('layouts.app')

@section('body_class', 'app-shell auth-layout')

@section('body')
    <main id="main-content" class="auth-shell">
        <section class="auth-panel" aria-labelledby="auth-page-title">
            <div class="auth-brand">
                <a class="brand" href="{{ url('/') }}" aria-label="PROXIWORK — accueil">
                    <span class="brand-mark" aria-hidden="true">
                        <i class="fa-solid fa-link"></i>
                    </span>
                    <span>PROXIWORK</span>
                </a>
            </div>

            @yield('auth_content')
        </section>
    </main>
@endsection
