<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $credentials['email'] = mb_strtolower(trim($credentials['email']));
        $remember = $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            $auditLogService->record('auth.web.login_failed', null, null, [], $request);
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        $user = Auth::guard('web')->user();

        if ($user === null || $user->account_status !== UserAccountStatus::ACTIVE) {
            $auditLogService->record('auth.web.login_blocked', $user, $user, ['reason' => 'inactive_account'], $request);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Impossible de vous connecter avec ce compte. Vérifiez son état ou contactez le support.',
            ]);
        }

        // Rotate the session identifier immediately after authentication to prevent fixation.
        $request->session()->forget('active_workspace');
        $request->session()->regenerate();
        $request->session()->put('auth.session_version', (int) $user->session_version);
        $auditLogService->record('auth.web.login_succeeded', $user, $user, [], $request);

        // The destination is resolved from server-side roles/permissions, never from a
        // role or destination supplied by the browser. Professional verification status
        // is handled inside the professional workspace, not by granting public visibility.
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isSafeInternalRedirect($intended, $user)) {
            return redirect()->to($intended);
        }

        return redirect()->route('dashboard');
    }

    private function isSafeInternalRedirect(string $url, User $user): bool
    {
        if (str_starts_with($url, '/') && ($url[1] ?? '') !== '/' && ($url[1] ?? '') !== chr(92)) {
            return true;
        }

        $targetHost = parse_url($url, PHP_URL_HOST);
        $applicationHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $applicationScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        $isSameOrigin = is_string($targetHost)
            && is_string($applicationHost)
            && is_string($scheme)
            && $scheme === $applicationScheme
            && hash_equals(strtolower($applicationHost), strtolower($targetHost));

        if (! $isSameOrigin) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return false;
        }

        if (str_starts_with($path, '/client/') || $path === '/client') {
            return $user->hasRole('client');
        }

        if (str_starts_with($path, '/professional/') || $path === '/professional') {
            return $user->hasRole('professional');
        }

        if (str_starts_with($path, '/admin/') || $path === '/admin') {
            return $user->hasPermissionTo('admin.dashboard.view') || $user->hasPermissionTo('rbac.view');
        }

        return true;
    }

    public function destroy(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $auditLogService->record('auth.web.logout', $user, $user, [], $request);
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Vous êtes déconnecté de PROXIWORK.');
    }
}
