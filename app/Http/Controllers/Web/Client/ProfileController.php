<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Client\RequestClientEmailChange;
use App\Http\Requests\Web\Client\UpdateClientPasswordRequest;
use App\Http\Requests\Web\Client\UpdateClientProfileRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewContract;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('profile');

        return view('client.profile.show', compact('user'));
    }

    public function edit(Request $request): View
    {
        $user = $request->user()->load('profile');

        return view('client.profile.edit', compact('user'));
    }

    public function update(UpdateClientProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['avatar_path'], $data['email'], $data['role'], $data['account_status']);

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()
                ->whereKey($request->user()->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $user->profile()->updateOrCreate([], $data);
        });

        return redirect()
            ->route('client.profile')
            ->with('success', 'Vos informations personnelles ont été enregistrées.');
    }

    public function photo(Request $request): BinaryFileResponse
    {
        $profile = $request->user()->profile;
        $path = $profile?->avatar_path;

        abort_unless(
            is_string($path) && $path !== '' && Storage::disk('local')->exists($path),
            404,
        );

        $mimeType = Storage::disk('local')->mimeType($path);
        abort_unless(is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=2000,max_height=2000',
            ],
        ]);

        $user = $request->user();
        $newPath = $validated['avatar']->store('client-avatars/'.$user->getKey(), 'local');
        $oldPath = $user->profile?->avatar_path;

        try {
            DB::transaction(function () use ($user, $newPath): void {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $profile = $lockedUser->profile()->firstOrCreate([]);
                $profile->forceFill(['avatar_path' => $newPath])->save();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('client.profile.edit')->with('success', 'Photo de profil mise à jour.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->profile?->avatar_path;

        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $profile = $lockedUser->profile;

            if ($profile !== null) {
                $profile->forceFill(['avatar_path' => null])->save();
            }
        });

        if (is_string($oldPath) && $oldPath !== '') {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('client.profile.edit')->with('success', 'Photo de profil supprimée.');
    }

    public function requestEmailChange(RequestClientEmailChange $request): RedirectResponse
    {
        $user = $request->user();
        $pendingEmail = $request->validated('email');
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        try {
            DB::transaction(function () use ($user, $pendingEmail, $tokenHash): void {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $lockedUser->forceFill([
                    'pending_email' => $pendingEmail,
                    'pending_email_token_hash' => $tokenHash,
                    'pending_email_expires_at' => now()->addMinutes(30),
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::warning('Could not persist a client email-change request.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => 'Cette adresse vient peut-être d’être réservée par une autre demande. Vérifiez l’adresse ou réessayez.',
            ])->withInput($request->safe()->except(['current_password']));
        }

        try {
            Notification::route('mail', $pendingEmail)->notify(new \App\Notifications\EmailAddressChangeNotification(
                'Confirmez votre nouvelle adresse e-mail',
                'Une demande de changement d’adresse e-mail a été effectuée pour votre compte PROXIWORK. L’adresse actuelle reste active tant que vous n’avez pas confirmé ce lien. Le lien expire dans 30 minutes.',
                route('client.profile.email-change.confirmation', ['token' => $token]),
                'Confirmer la nouvelle adresse',
            ));
        } catch (Throwable $exception) {
            DB::transaction(function () use ($user, $tokenHash): void {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                if ($lockedUser !== null && hash_equals((string) $lockedUser->pending_email_token_hash, $tokenHash)) {
                    $lockedUser->forceFill([
                        'pending_email' => null,
                        'pending_email_token_hash' => null,
                        'pending_email_expires_at' => null,
                    ])->save();
                }
            });

            Log::warning('Could not dispatch a client email-change confirmation.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => 'Le message de confirmation n’a pas pu être envoyé. Aucune modification de votre adresse actuelle n’a été effectuée.',
            ])->withInput($request->safe()->except(['current_password']));
        }

        return redirect()->route('client.profile.edit')->with(
            'success',
            'Un lien de confirmation a été demandé pour la nouvelle adresse. Votre adresse actuelle reste active jusqu’à la confirmation.',
        );
    }

    public function showEmailChangeConfirmation(Request $request, string $token): RedirectResponse|ViewContract
    {
        $user = $request->user();
        $hash = hash('sha256', $token);

        abort_unless(
            is_string($user->pending_email_token_hash)
                && hash_equals($user->pending_email_token_hash, $hash)
                && $user->pending_email_expires_at !== null
                && $user->pending_email_expires_at->isFuture()
                && is_string($user->pending_email),
            403,
            'Ce lien de confirmation est invalide ou a expiré.',
        );

        return view('client.profile.email-change-confirmation', [
            'pendingEmail' => $user->pending_email,
            'token' => $token,
        ]);
    }

    public function confirmEmailChange(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        $tokenHash = hash('sha256', $token);
        $oldEmail = $user->email;
        $confirmedEmail = null;

        $result = DB::transaction(function () use ($user, $tokenHash, &$confirmedEmail): string {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (
                ! is_string($lockedUser->pending_email_token_hash)
                || ! hash_equals($lockedUser->pending_email_token_hash, $tokenHash)
                || $lockedUser->pending_email_expires_at === null
                || $lockedUser->pending_email_expires_at->isPast()
                || ! is_string($lockedUser->pending_email)
            ) {
                return 'invalid';
            }

            $pendingEmail = $lockedUser->pending_email;

            if (User::query()->where('email', $pendingEmail)->whereKeyNot($lockedUser->getKey())->exists()) {
                $lockedUser->forceFill([
                    'pending_email' => null,
                    'pending_email_token_hash' => null,
                    'pending_email_expires_at' => null,
                ])->save();

                return 'taken';
            }

            $lockedUser->forceFill([
                'email' => $pendingEmail,
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_token_hash' => null,
                'pending_email_expires_at' => null,
            ])->save();

            $confirmedEmail = $pendingEmail;

            return 'confirmed';
        });

        if ($result === 'invalid') {
            return redirect()->route('client.profile.edit')->withErrors([
                'email' => 'Ce lien est invalide ou a expiré. Demandez une nouvelle confirmation.',
            ]);
        }

        if ($result === 'taken') {
            return redirect()->route('client.profile.edit')->withErrors([
                'email' => 'Cette adresse est maintenant utilisée par un autre compte. Votre adresse actuelle n’a pas été modifiée.',
            ]);
        }

        $user->refresh();
        $user->tokens()->delete();
        $request->session()->regenerate();

        try {
            Notification::route('mail', $oldEmail)->notify(new \App\Notifications\EmailAddressChangeNotification(
                'Adresse e-mail du compte PROXIWORK modifiée',
                'L’adresse e-mail de votre compte PROXIWORK a été modifiée après confirmation. Si vous n’êtes pas à l’origine de cette action, contactez le support.',
            ));
        } catch (Throwable $exception) {
            Log::warning('Could not dispatch the old-address email-change notice.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);
        }

        return redirect()->route('client.profile')->with(
            'success',
            'Votre nouvelle adresse e-mail est confirmée et vérifiée : '.$confirmedEmail.'.',
        );
    }

    public function updatePassword(UpdateClientPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        // Revoke API tokens and other database-backed sessions, but keep this session active.
        $user->tokens()->delete();
        $request->session()->regenerate();

        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        try {
            $user->notify(new AccountActivityNotification(
                'Mot de passe modifié',
                'Le mot de passe de votre compte PROXIWORK vient d’être modifié. Si vous n’êtes pas à l’origine de cette action, sécurisez immédiatement votre compte.',
                'password_changed',
                route('client.profile'),
            ));
        } catch (Throwable $exception) {
            Log::warning('Could not dispatch the client password-change notification.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);
        }

        return redirect()->route('client.profile.edit')->with('success', 'Mot de passe modifié. Les autres sessions et les jetons API ont été révoqués.');
    }
}
