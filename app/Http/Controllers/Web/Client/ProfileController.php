<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Client\UpdateClientPasswordRequest;
use App\Http\Requests\Web\Client\UpdateClientProfileRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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
