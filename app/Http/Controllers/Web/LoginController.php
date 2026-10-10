<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Web;

use App\\Enums\\UserAccountStatus;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Validation\\ValidationException;
use Illuminate\\View\\View;

class LoginController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $credentials = [
            'email' => mb_strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        $user = Auth::user();

        if ($user === null || $user->account_status !== UserAccountStatus::ACTIVE) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Deliberately use the same public message as invalid credentials.
            // This avoids disclosing whether an account exists or is restricted.
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects ou ce compte ne peut pas se connecter.',
            ]);
        }

        $request->session()->regenerate();

        // The dashboard resolves the destination from the roles assigned on the server.
        // Do not trust a role or destination supplied by the browser.
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Vous avez été déconnecté avec succès.');
    }
}
