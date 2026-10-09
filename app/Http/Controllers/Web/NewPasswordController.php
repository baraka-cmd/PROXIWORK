<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
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

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez un nouveau lien.']);
    }
}
