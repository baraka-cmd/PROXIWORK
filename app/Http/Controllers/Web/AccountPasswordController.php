<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-password', [
            'user' => $request->user(),
        ]);
    }

    public function update(
        UpdatePasswordRequest $request,
        AuditLogService $auditLogService,
        SessionRevocationService $sessionRevocationService,
    ): RedirectResponse {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $sessionRevocationService->revokeAll($user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $auditLogService->record('auth.web.password_changed', $user, $user, [], $request);
        $user->notify(new AccountActivityNotification(
            'Mot de passe modifié',
            'Le mot de passe de votre compte PROXIWORK a été modifié. Si vous n’êtes pas à l’origine de ce changement, contactez immédiatement le support.',
            'password_changed',
        ));

        return redirect()->route('login')->with(
            'status',
            'Votre mot de passe a été modifié. Pour votre sécurité, toutes les sessions ont été révoquées ; reconnectez-vous.'
        );
    }
}
