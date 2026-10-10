<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeEmailRequest;
use App\Models\User;
use App\Notifications\EmailAddressChangedNotification;
use App\Notifications\PendingEmailChangeNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class EmailChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-email', [
            'user' => $request->user(),
        ]);
    }

    public function store(
        ChangeEmailRequest $request,
        AuditLogService $auditLogService,
    ): RedirectResponse {
        $user = $request->user();
        $email = $request->validated('email');

        if (hash_equals(mb_strtolower($user->email), $email)) {
            return back()->withErrors([
                'email' => 'Cette adresse est déjà celle de votre compte.',
            ])->withInput($request->only('email'));
        }

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            return back()->withErrors([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ])->withInput($request->only('email'));
        }

        $user->forceFill(['pending_email' => $email])->save();

        $url = URL::temporarySignedRoute(
            'web.email-change.confirm',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($email)],
        );

        Notification::route('mail', $email)->notify(
            new PendingEmailChangeNotification($url, $user->name)
        );

        $auditLogService->record('auth.web.email_change_requested', $user, $user, [], $request);

        return back()->with(
            'status',
            'Un lien de confirmation a été envoyé à la nouvelle adresse. Votre adresse actuelle reste active jusqu’à confirmation.'
        );
    }

    public function confirm(
        Request $request,
        int $id,
        string $hash,
        AuditLogService $auditLogService,
        SessionRevocationService $sessionRevocationService,
    ): RedirectResponse {
        $authenticatedUser = $request->user();

        abort_unless($authenticatedUser instanceof User && (int) $authenticatedUser->getKey() === $id, 403);

        $user = User::query()->findOrFail($id);
        $pendingEmail = $user->pending_email;

        abort_unless(
            is_string($pendingEmail)
                && $pendingEmail !== ''
                && hash_equals(sha1($pendingEmail), $hash),
            403,
            'Ce lien de confirmation est invalide ou a expiré.',
        );

        if (User::query()->where('email', $pendingEmail)->where('id', '!=', $id)->exists()) {
            return redirect()->route('account.email.edit')
                ->withErrors(['email' => 'Cette adresse est maintenant associée à un autre compte. Demandez une nouvelle confirmation.']);
        }

        $oldEmail = $user->email;

        try {
            DB::transaction(function () use ($user, $pendingEmail): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

                abort_unless(
                    is_string($lockedUser->pending_email)
                        && hash_equals(sha1($lockedUser->pending_email), sha1($pendingEmail)),
                    403,
                    'La demande de changement d’adresse a été remplacée.',
                );

                $lockedUser->forceFill([
                    'email' => $pendingEmail,
                    'email_verified_at' => now(),
                    'pending_email' => null,
                ])->save();
            });
        } catch (QueryException) {
            return redirect()->route('account.email.edit')
                ->withErrors(['email' => 'Cette adresse ne peut plus être utilisée. Demandez une nouvelle confirmation.']);
        }

        $user->refresh();
        $sessionRevocationService->revokeAll($user);
        auth('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $auditLogService->record('auth.web.email_changed', $user, $user, [], $request);
        Notification::route('mail', $oldEmail)->notify(
            new EmailAddressChangedNotification($user->name, $user->email)
        );

        return redirect()->route('login')->with(
            'status',
            'Votre nouvelle adresse e-mail est confirmée. Pour votre sécurité, connectez-vous à nouveau.'
        );
    }
}
