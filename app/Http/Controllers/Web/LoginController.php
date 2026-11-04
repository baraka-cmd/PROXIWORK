<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\UserAccountStatus;
use App\Http\Requests\Web\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController
{
    public function create(Request $request): View
    {
        $continuation = $request->query('continue');

        if ($this->isSafeIntendedUrl($request, $continuation)) {
            $request->session()->put('url.intended', $continuation);
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $remember = (bool) ($credentials['remember'] ?? false);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        $user = Auth::user();

        if ($user === null || $user->account_status !== UserAccountStatus::ACTIVE) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Impossible de vous connecter avec ce compte.',
            ]);
        }

        $request->session()->regenerate();

        $request->session()->forget('active_workspace');

        return redirect()->to($this->safeIntendedUrl($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Vous êtes maintenant déconnecté.');
    }

    private function safeIntendedUrl(Request $request): string
    {
        $fallback = route('dashboard');
        $intended = $request->session()->pull('url.intended');

        return $this->isSafeIntendedUrl($request, $intended) ? $intended : $fallback;
    }

    private function isSafeIntendedUrl(Request $request, mixed $intended): bool
    {
        if (! is_string($intended) || $intended === '' || str_contains($intended, '\\')) {
            return false;
        }

        if (str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
            return true;
        }

        $parts = parse_url($intended);
        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return false;
        }

        $requestScheme = $request->isSecure() ? 'https' : 'http';
        $requestPort = $request->getPort();
        $intendedPort = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);

        if (strtolower($parts['scheme']) !== $requestScheme
            || strtolower($parts['host']) !== strtolower($request->getHost())
            || $intendedPort !== $requestPort) {
            return false;
        }

        return true;
    }
}
