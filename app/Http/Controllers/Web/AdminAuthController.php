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

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            $audit->recordAuthenticationFailure($credentials['email'], $request, null, 'admin_invalid_credentials');
            throw ValidationException::withMessages(['email' => 'Les identifiants fournis sont incorrects.']);
        }

        $user = Auth::guard('web')->user();

        if ($user->account_status !== UserAccountStatus::ACTIVE
            || (! $user->hasRole('admin') && ! $user->hasPermissionTo('rbac.view'))) {
            $audit->recordAuthenticationFailure($credentials['email'], $request, $user, 'admin_access_denied');
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Ce compte n’est pas autorisé à accéder à l’administration.',
            ]);
        }

        $request->session()->regenerate();
        $audit->recordEvent('admin.auth.login', $user, $user, [], $request, category: 'authentication');

        return redirect()->route('admin.rbac.dashboard');
    }

    public function destroy(Request $request, AuditLogService $audit): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if ($user !== null) {
            $audit->recordEvent('admin.auth.logout', $user, $user, [], $request, category: 'authentication');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
