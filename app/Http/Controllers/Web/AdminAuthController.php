<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Web;

use App\\Enums\\UserAccountStatus;
use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Validation\\ValidationException;
use Illuminate\\View\\View;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
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

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        $user = Auth::guard('web')->user();

        if (
            $user === null
            || $user->account_status !== UserAccountStatus::ACTIVE
            || (! $user->hasRole('admin') && ! $user->hasPermissionTo('rbac.view'))
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects ou ce compte ne peut pas accéder à cet espace.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Vous avez été déconnecté avec succès.');
    }
}
