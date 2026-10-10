<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            $auditLogService->record('auth.admin.login_failed', null, null, [], $request);
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        $user = Auth::guard('web')->user();

        if ($user === null || $user->account_status !== UserAccountStatus::ACTIVE) {
            $auditLogService->record('auth.admin.login_blocked', $user, $user, ['reason' => 'inactive_account'], $request);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Impossible de vous connecter avec ce compte.',
            ]);
        }

        if (! $user->hasRole('admin') && ! $user->hasPermissionTo('rbac.view')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        $request->session()->forget('active_workspace');
        $request->session()->regenerate();
        $auditLogService->record('auth.admin.login_succeeded', $user, $user, [], $request);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $auditLogService->record('auth.admin.logout', $user, $user, [], $request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Vous êtes déconnecté de PROXIWORK.');
    }
}
