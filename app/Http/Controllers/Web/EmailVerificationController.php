<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\User;

class EmailVerificationController
{
    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash, AuditLogService $auditLogService): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless($user->isActive(), 403, 'Ce compte ne peut pas être vérifié dans son état actuel.');
        abort_if($request->user() !== null && ! $request->user()->is($user), 403, 'Ce lien appartient à un autre compte.');
        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Le lien de vérification ne correspond pas à cette adresse e-mail.'
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            $auditLogService->record('auth.web.email_verified', $user, $user, [], $request);
            event(new Verified($user));
        }

        if ($request->user()?->is($user)) {
            return redirect()->intended(route('dashboard'))
                ->with('status', 'Votre adresse e-mail a été vérifiée avec succès.');
        }

        return redirect()->route('login')->with(
            'status',
            'Votre adresse e-mail a été vérifiée avec succès. Vous pouvez maintenant vous connecter.'
        );
    }

    public function resend(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'))
                ->with('status', 'Votre adresse e-mail est déjà vérifiée.');
        }

        $user->sendEmailVerificationNotification();
        $auditLogService->record('auth.web.email_verification_requested', $user, $user, [], $request);

        return back()->with(
            'status',
            'Si cette adresse peut recevoir les messages de vérification, un nouveau lien vient d’être envoyé.'
        );
    }
}
