<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController
{
    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request, AuditLogService $auditLogService): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            $auditLogService->record('auth.web.email_verified', $user, $user, [], $request);
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard'))
            ->with('status', 'Votre adresse e-mail a été vérifiée avec succès.');
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
