<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

    public function store(ResetPasswordRequest $request, TransactionalNotificationService $notificationService): RedirectResponse
    {
        $resetUser = null;

        $status = DB::transaction(function () use ($request, &$resetUser, $notificationService) {
            return Password::reset($request->validated(), function (User $user, string $password) use (&$resetUser, $notificationService): void {
                $resetUser = $user;
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->getKey())->delete();

                event(new PasswordReset($user));
                $notificationService->securityAlert(
                    $user,
                    'Mot de passe réinitialisé',
                    'Votre mot de passe a été réinitialisé. Si vous n’êtes pas à l’origine de cette action, contactez immédiatement le support.',
                    null,
                    'password-reset:'.$user->getKey().':'.(string) $user->updated_at?->timestamp,
                );
            });
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez un nouveau lien.']);
    }
}
