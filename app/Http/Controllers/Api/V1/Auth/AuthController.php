<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => mb_strtolower($request->string('email')->toString()),
                'password' => $request->string('password')->toString(),
            ]);

            $user->profile()->create();
            $user->assignRole(Role::where('name', 'client')->firstOrFail());

            return $user;
        });

        event(new Registered($user));

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre adresse e-mail.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = mb_strtolower($request->string('email')->toString());
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Déconnexion réussie.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Utilisateur authentifié.',
            'data' => new UserResource($request->user()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        $user->tokens()->delete();

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        return response()->json([
            'message' => 'Mot de passe modifié. Les anciennes sessions ont été révoquées.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc']]);

        $status = Password::sendResetLink([
            'email' => mb_strtolower($request->string('email')->toString()),
        ]);

        if ($status !== Password::ResetLinkSent) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Si cette adresse existe, un lien de réinitialisation a été envoyé.',
            'data' => null,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
            'data' => null,
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'L’adresse e-mail est déjà vérifiée.',
                'data' => null,
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Un nouveau lien de vérification a été envoyé.',
            'data' => null,
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Lien de vérification invalide.'
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'message' => 'Adresse e-mail vérifiée avec succès.',
            'data' => ['user' => new UserResource($user->fresh())],
        ]);
    }
}
