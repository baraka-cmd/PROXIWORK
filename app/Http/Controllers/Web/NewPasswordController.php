<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController
{
    public function create(string $token): View
    {
        return view('auth.passwords.reset', [
            'token' => $token,
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogService $auditLogService, SessionRevocationService $sessionRevocationService): RedirectResponse
    {
        $resetUser = null;

        $status = Password::reset($request->validated(), function (User $user, string $password) use (&$resetUser, $sessionRevocationService): void {
            $resetUser = $user;
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $sessionRevocationService->revokeAll($user);
            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET && $resetUser instanceof User) {
            $auditLogService->record('auth.web.password_reset', $resetUser, $resetUser, [], $request);
            $resetUser->notify(new AccountActivityNotification(
                'Mot de passe réinitialisé',
                'Le mot de passe de votre compte PROXIWORK a été réinitialisé. Si vous n’êtes pas à l’origine de ce changement, contactez immédiatement le support.',
                'password_reset',
            ));

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Votre mot de passe a été réinitialisé. Connectez-vous avec votre nouveau mot de passe.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez un nouveau lien.']);
    }
}
