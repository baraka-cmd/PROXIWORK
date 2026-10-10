<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController
{
    public function create(): View
    {
        return view('auth.passwords.email');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            Log::warning('Password reset notification could not be dispatched.', [
                'exception' => $exception::class,
            ]);

            return back()->with('status', 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d’être envoyé.');
        }

        // Do not reveal whether an email address belongs to a registered user.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return back()->with('status', 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d’être envoyé.');
        }

        return back()->withErrors([
            'email' => 'Impossible de traiter la demande pour le moment. Réessayez plus tard.',
        ]);
    }
}
