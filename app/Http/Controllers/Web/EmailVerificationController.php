<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless($request->user()->is($user), 403);
        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Lien de vérification invalide.',
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('dashboard')->with(
            'status',
            'Votre adresse e-mail est maintenant vérifiée.',
        );
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back()->with('status', 'Votre adresse e-mail est déjà vérifiée.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with(
            'status',
            'Un nouveau lien de vérification a été envoyé à votre adresse e-mail.',
        );
    }
}